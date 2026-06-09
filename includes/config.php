<?php
// Desenvolvido pelo Sr. Engenheiro João

declare(strict_types=1);

/**
 * Configuração central — ajuste conforme o servidor.
 */
return [
    // Caminho absoluto ou relativo ao documento web (public/)
    'base_path' => dirname(__DIR__),

    'uploads' => [
        'temp' => dirname(__DIR__) . '/uploads/temp',
        'compressed' => dirname(__DIR__) . '/uploads/compressed',
    ],

    /** Tamanho máximo por ficheiro (bytes) — ex.: 50 MB */
    'max_file_bytes' => 50 * 1024 * 1024,

    /** Total máximo por pedido de upload (bytes) */
    'max_batch_bytes' => 5 * 1024 * 1024 * 1024,

    /** Máximo de ficheiros num único upload */
    'max_files_per_upload' => 1000,

    /** Máximo de compressões paralelas (processos Ghostscript em simultâneo) */
    'max_parallel_compression' => 100,

    /**
     * Ficheiros apagados automaticamente após este tempo (minutos)
     * se não forem descarregados.
     */
    'ttl_minutes' => 30,

    /**
     * Executável Ghostscript (Linux/Ubuntu: "gs" no PATH, ou ex. "/usr/bin/gs").
     * Instalação típica: sudo apt install ghostscript
     */
    'ghostscript_bin' => 'gs',

    /**
     * Níveis → -dPDFSETTINGS do Ghostscript
     */
    'pdf_settings' => [
        'low' => '/printer',   // baixa qualidade (ficheiro maior, melhor qualidade)
        'medium' => '/ebook',
        'high' => '/screen', // alta compressão (ficheiro menor)
    ],

    'session_name' => 'pdfsucker_sid',
];
