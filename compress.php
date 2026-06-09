<?php
// Desenvolvido pelo Sr. Engenheiro João

declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    appJsonResponse(['ok' => false, 'error' => 'Método não permitido.'], 405);
}

$data = readJsonRequestBody();
if ($data === null) {
    appJsonResponse(['ok' => false, 'error' => 'Pedido inválido.'], 400);
}

$level = (string) ($data['level'] ?? 'medium');
$map = $config['pdf_settings'] ?? [];
if (!isset($map[$level])) {
    appJsonResponse(['ok' => false, 'error' => 'Nível de compressão inválido.'], 400);
}
$pdfSetting = $map[$level];

$ids = $data['ids'] ?? null;
if (!is_array($ids) || $ids === []) {
    appJsonResponse(['ok' => false, 'error' => 'Nenhum ficheiro selecionado.'], 400);
}

$maxBatchIds = (int) ($config['max_files_per_upload'] ?? 20);
if (count($ids) > $maxBatchIds) {
    appJsonResponse(['ok' => false, 'error' => 'Demasiados ficheiros num único pedido.'], 400);
}

// Maiores primeiro — independentemente da ordem do pedido
usort($ids, static function ($a, $b): int {
    if (!is_string($a) || !is_string($b)) {
        return 0;
    }
    $sizeA = (int) ($_SESSION['files'][$a]['original_size'] ?? 0);
    $sizeB = (int) ($_SESSION['files'][$b]['original_size'] ?? 0);
    return $sizeB <=> $sizeA;
});

[$gsBin, $gsOk] = resolveGhostscriptBinary((string) ($config['ghostscript_bin'] ?? 'gs'));
if (!$gsOk) {
    appJsonResponse([
        'ok' => false,
        'error' => 'O Ghostscript não está disponível no servidor. Em Ubuntu: sudo apt install ghostscript',
    ], 503);
}

$outDir = $config['uploads']['compressed'];
$tempDir = $config['uploads']['temp'];
ensureDir($outDir);
ensureDir($tempDir);
$rpOutDir = realpath($outDir);
$rpTempDir = realpath($tempDir);
if ($rpOutDir === false || $rpTempDir === false) {
    appJsonResponse(['ok' => false, 'error' => 'Diretórios de trabalho indisponíveis.'], 500);
}

$results = [];
$maxParallel = (int) ($config['max_parallel_compression'] ?? 100);

/**
 * Processa um ficheiro individual para compressão
 * @param string $gsBin Caminho para o binário Ghostscript
 * @param string $pdfSetting Nível de compressão PDF
 * @param string $id ID do ficheiro
 * @param string $rpOutDir Caminho real do diretório de saída
 * @param string $rpTempDir Caminho real do diretório temporário
 * @return array Resultado da compressão
 */
function compressFile($gsBin, $pdfSetting, $id, $rpOutDir, $rpTempDir) {
    if (!is_string($id) || !preg_match('/^[a-f0-9]{32}$/', $id)) {
        return ['ok' => false, 'id' => $id, 'error' => 'Identificador inválido.'];
    }

    if (!isset($_SESSION['files'][$id]) || !is_array($_SESSION['files'][$id])) {
        return ['ok' => false, 'id' => $id, 'error' => 'Ficheiro não encontrado ou expirado.'];
    }

    $meta = $_SESSION['files'][$id];
    $input = $meta['original_path'] ?? '';
    if (!is_string($input) || !pathIsFileInsideDir($input, $rpTempDir)) {
        return ['ok' => false, 'id' => $id, 'error' => 'Ficheiro original em falta.'];
    }

    // Remover compressão anterior se existir
    unlinkUploadPathIfExists(isset($meta['compressed_path']) ? (string) $meta['compressed_path'] : null, ['uploads' => ['compressed' => $rpOutDir]]);

    $outName = $id . '_compressed.pdf';
    if (!preg_match('/^[a-f0-9]{32}_compressed\.pdf$/', $outName)) {
        return ['ok' => false, 'id' => $id, 'error' => 'Estado interno inválido.'];
    }
    $output = $rpOutDir . DIRECTORY_SEPARATOR . $outName;

    $cmd = escapeshellarg($gsBin)
        . ' -sDEVICE=pdfwrite'
        . ' -dCompatibilityLevel=1.4'
        . ' -dSAFER'
        . ' -dPDFSETTINGS=' . $pdfSetting
        . ' -dNOPAUSE -dQUIET -dBATCH'
        . ' -sOutputFile=' . escapeshellarg($output)
        . ' ' . escapeshellarg($input)
        . ' 2>&1';

    $outputLog = [];
    $code = 0;
    exec($cmd, $outputLog, $code);   

    if ($code !== 0 || !is_file($output) || is_link($output) || !pathIsFileInsideDir($output, $rpOutDir) || filesize($output) === 0) {
        unlinkUploadPathIfExists(is_file($output) ? $output : null, ['uploads' => ['compressed' => $rpOutDir]]);
        return [
            'ok' => false,
            'id' => $id,
            'name' => (string) ($meta['original_name'] ?? ''),
            'error' => 'Falha ao executar o Ghostscript. Verifique o ficheiro e tente novamente.',
        ];
    }

    @chmod($output, 0640);

    $origSize = (int) ($meta['original_size'] ?? filesize($input) ?: 0);
    $newSize = (int) filesize($output);
    $reduction = $origSize > 0 ? round((1 - $newSize / $origSize) * 100, 1) : 0.0;

    $_SESSION['files'][$id]['compressed_path'] = $output;
    $_SESSION['files'][$id]['compressed_size'] = $newSize;

    return [
        'ok' => true,
        'id' => $id,
        'name' => (string) ($meta['original_name'] ?? ''),
        'original_size' => $origSize,
        'compressed_size' => $newSize,
        'reduction_percent' => $reduction,
    ];
}

// Usar processamento paralelo se suportado
if (function_exists('proc_open') && $maxParallel > 1) {
    // Processar em batches paralelos
    $processes = [];
    $processMap = [];
    $idIndex = 0;

    while ($idIndex < count($ids) || !empty($processes)) {
        // Iniciar novos processos até ao limite
        while ($idIndex < count($ids) && count($processes) < $maxParallel) {
            $id = $ids[$idIndex];
            if (!is_string($id) || !preg_match('/^[a-f0-9]{32}$/', $id)) {
                $results[] = ['ok' => false, 'id' => $id, 'error' => 'Identificador inválido.'];
                $idIndex++;
                continue;
            }

            if (!isset($_SESSION['files'][$id]) || !is_array($_SESSION['files'][$id])) {
                $results[] = ['ok' => false, 'id' => $id, 'error' => 'Ficheiro não encontrado ou expirado.'];
                $idIndex++;
                continue;
            }

            // Iniciar o processo de compressão em background
            $meta = $_SESSION['files'][$id];
            $input = $meta['original_path'] ?? '';
            $outName = $id . '_compressed.pdf';
            $output = $rpOutDir . DIRECTORY_SEPARATOR . $outName;

            $cmd = escapeshellarg($gsBin)
                . ' -sDEVICE=pdfwrite'
                . ' -dCompatibilityLevel=1.4'
                . ' -dSAFER'
                . ' -dPDFSETTINGS=' . $pdfSetting
                . ' -dNOPAUSE -dQUIET -dBATCH'
                . ' -sOutputFile=' . escapeshellarg($output)
                . ' ' . escapeshellarg($input)
                . ' 2>&1';

            $proc = @proc_open($cmd, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
            if (is_resource($proc)) {
                $processes[$id] = ['proc' => $proc, 'pipes' => $pipes, 'output' => $output, 'input' => $input, 'startTime' => time()];
                $processMap[$id] = true;
            } else {
                $results[] = [
                    'ok' => false,
                    'id' => $id,
                    'name' => (string) ($meta['original_name'] ?? ''),
                    'error' => 'Não foi possível iniciar o processo de compressão.',
                ];
            }

            $idIndex++;
        }

        // Verificar processos concluídos
        foreach ($processes as $id => $proc_data) {
            $status = proc_get_status($proc_data['proc']);
            
            if (!$status['running']) {
                // Processo concluído
                fclose($proc_data['pipes'][1]);
                fclose($proc_data['pipes'][2]);
                $exitCode = $status['exitcode'];
                proc_close($proc_data['proc']);

                if ($exitCode === 0 && is_file($proc_data['output']) && filesize($proc_data['output']) > 0) {
                    @chmod($proc_data['output'], 0640);
                    
                    $meta = $_SESSION['files'][$id];
                    $origSize = (int) ($meta['original_size'] ?? filesize($proc_data['input']) ?: 0);
                    $newSize = (int) filesize($proc_data['output']);
                    $reduction = $origSize > 0 ? round((1 - $newSize / $origSize) * 100, 1) : 0.0;

                    $_SESSION['files'][$id]['compressed_path'] = $proc_data['output'];
                    $_SESSION['files'][$id]['compressed_size'] = $newSize;

                    $results[] = [
                        'ok' => true,
                        'id' => $id,
                        'name' => (string) ($meta['original_name'] ?? ''),
                        'original_size' => $origSize,
                        'compressed_size' => $newSize,
                        'reduction_percent' => $reduction,
                    ];
                } else {
                    unlinkUploadPathIfExists(is_file($proc_data['output']) ? $proc_data['output'] : null, ['uploads' => ['compressed' => $rpOutDir]]);
                    $meta = $_SESSION['files'][$id];
                    $results[] = [
                        'ok' => false,
                        'id' => $id,
                        'name' => (string) ($meta['original_name'] ?? ''),
                        'error' => 'Falha ao executar o Ghostscript. Verifique o ficheiro e tente novamente.',
                    ];
                }

                unset($processes[$id]);
            } else if (time() - $proc_data['startTime'] > 300) {
                // Timeout de 5 minutos
                proc_terminate($proc_data['proc']);
                fclose($proc_data['pipes'][1]);
                fclose($proc_data['pipes'][2]);
                proc_close($proc_data['proc']);
                
                unlinkUploadPathIfExists(is_file($proc_data['output']) ? $proc_data['output'] : null, ['uploads' => ['compressed' => $rpOutDir]]);
                $meta = $_SESSION['files'][$id];
                $results[] = [
                    'ok' => false,
                    'id' => $id,
                    'name' => (string) ($meta['original_name'] ?? ''),
                    'error' => 'Compressão expirou por timeout.',
                ];

                unset($processes[$id]);
            }
        }

        // Pequena pausa para evitar uso excessivo de CPU
        if (!empty($processes)) {
            usleep(100000); // 100ms
        }
    }
} else {
    // Processamento sequencial (fallback)
    foreach ($ids as $id) {
        $results[] = compressFile($gsBin, $pdfSetting, $id, $rpOutDir, $rpTempDir);
    }
}

appJsonResponse(['ok' => true, 'results' => $results]);
