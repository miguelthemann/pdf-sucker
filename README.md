# PDF Sucker 🌬️ — Edição SCPDPI

<p align="center">
  <img src="https://img.shields.io/github/actions/workflow/status/miguelthemann/pdf-sucker/docker-publish-scpdpi.yml?branch=scpdpi&style=for-the-badge&logo=github&label=Build%20(scpdpi)" />
  <img src="https://img.shields.io/badge/Docker%20Image-ghcr.io%2Fpdf--sucker--scpdpi-2496ED?style=for-the-badge&logo=docker&logoColor=white" />
</p>

Aplicação web para compressão de ficheiros PDF no servidor. Reduz o tamanho dos PDFs mantendo a qualidade usando [Ghostscript](https://www.ghostscript.com/).

Esta é a branch **`scpdpi`**, uma variante personalizada do [pdf-sucker](https://github.com/miguelthemann/pdf-sucker) feita para uso interno na [scpdpi.com](https://scpdpi.com/), com tema próprio e configuração reestruturada.

**Desenvolvido por:** [João](https://github.com/JoaoTom1922) e [Miguel](https://github.com/miguelthemann) (14/05/2026)

## Recursos

- ✅ **Upload múltiplo** — até 1000 ficheiros por requisição
- ✅ **Compressão em servidor** — usa Ghostscript para reduzir tamanho
- ✅ **3 níveis de qualidade** — escolha entre qualidade vs tamanho
- ✅ **Interface web com tema SCPDPI** — visual azul, com branding e link para scpdpi.com
- ✅ **Limpeza automática** — ficheiros deletados após 30 minutos
- ✅ **Download direto** — descarregue PDFs comprimidos em ZIP (apenas ficheiros locais validados)
- ✅ **Containerizado** — deploy fácil com Docker, com correção automática de permissões em `uploads/` (útil em volumes geridos por Portainer, onde ficam com dono `root`)

## Requisitos

### Execução Local
- PHP 8.3+
- Apache com módulo `mod_rewrite`
- Ghostscript 10.0+
- Extensão PHP: `zip`

### Com Docker (Recomendado)
- Docker 20.10+
- Docker Compose 2.0+
- Portainer CE (opcional)

## Deploy com Docker

### Método 1: Docker Compose (build local — padrão desta branch)

```bash
# Clone o repositório e mude para a branch scpdpi
git clone https://github.com/miguelthemann/pdf-sucker.git
cd pdf-sucker
git checkout scpdpi

# Construa e inicie o serviço
docker-compose up -d --build

# Aceda em http://localhost:8080
```

**O que acontece:**
- A imagem é construída localmente a partir do `Dockerfile` (`pdf-sucker-scpdpi:local`)
- Apache é iniciado na porta 8080
- Ghostscript está pré-instalado
- Os uploads são persistidos no volume `uploads_data`
- No arranque, o `entrypoint.sh` corrige automaticamente o dono/permissões de `uploads/` para `www-data`

### Método 2: Imagem publicada via GHCR

Um workflow de CI (`docker-publish-scpdpi.yml`) publica automaticamente a imagem desta branch:

```bash
# Descarregar imagem
docker pull ghcr.io/miguelthemann/pdf-sucker-scpdpi:latest

# Executar contentor
docker run -d \
  --name pdf-sucker-scpdpi \
  -p 8080:80 \
  -v pdf-sucker-scpdpi-uploads:/var/www/html/uploads \
  ghcr.io/miguelthemann/pdf-sucker-scpdpi:latest

# Aceda em http://localhost:8080
```

> Nota: o nome da imagem é **`pdf-sucker-scpdpi`** (diferente da branch `main`, que usa `pdf-sucker`).

### Parar o serviço

```bash
# Se usou Docker Compose
docker-compose down

# Se usou Docker direto
docker stop pdf-sucker-scpdpi
docker rm pdf-sucker-scpdpi
```

## Configuração

Nesta branch, a configuração em `includes/config.php` foi reestruturada (deixou de ser uma lista simples de chaves — agora inclui caminhos, limites de lote e resolução do binário do Ghostscript):

```php
return [
    'base_path' => dirname(__DIR__),

    'uploads' => [
        'temp' => dirname(__DIR__) . '/uploads/temp',
        'compressed' => dirname(__DIR__) . '/uploads/compressed',
    ],

    // Tamanho máximo por ficheiro (padrão: 50 MB)
    'max_file_bytes' => 50 * 1024 * 1024,

    // Total máximo por pedido de upload (padrão: 5 GB)
    'max_batch_bytes' => 5 * 1024 * 1024 * 1024,

    // Máximo de ficheiros por upload (padrão: 1000)
    'max_files_per_upload' => 1000,

    // Máximo de compressões Ghostscript em paralelo
    'max_parallel_compression' => 4,

    // Tempo de expiração dos ficheiros (padrão: 30 minutos)
    'ttl_minutes' => 30,

    // Executável do Ghostscript (resolvido automaticamente se "gs" estiver no PATH)
    'ghostscript_bin' => 'gs',

    // Níveis de compressão PDF (-dPDFSETTINGS)
    'pdf_settings' => [
        'low' => '/printer',   // Baixa compressão, melhor qualidade
        'medium' => '/ebook',  // Balanço
        'high' => '/screen',   // Máxima compressão
    ],

    'session_name' => 'pdfsucker_sid',
];
```

A lógica de arranque (sessão, headers de segurança, limpeza de expirados) foi também separada para `includes/bootstrap.php`, e as funções auxiliares (resolução do binário Ghostscript, respostas JSON, contenção de caminhos em `uploads/`, etc.) para `includes/helpers.php`.

## Segurança

A descarga de PDFs passa por `download.php`. O caminho guardado na sessão **não** é passado diretamente a `is_file()`, `filesize()` nem `readfile()`:

- Caminhos com wrappers (`http://`, `php://`, etc.) são rejeitados, para o PHP não ir buscar um URL remoto (`allow_url_fopen`).
- O caminho é resolvido com `realpath()`.
- Só é servido se `pathIsFileInsideDir()` confirmar um ficheiro regular cujo caminho canónico está dentro de `uploads/temp` ou `uploads/compressed` (conforme o tipo pedido).

O Apache desta branch bloqueia acesso HTTP direto a `/uploads` (ver `docker/apache/zz-app.conf` e `.htaccess`). Relatórios de vulnerabilidades: ver [SECURITY.md](SECURITY.md).

## Utilização

1. **Abra** http://ip-da-máquina:8080
2. **Arraste ficheiros PDF** ou clique para selecionar
3. **Escolha o nível de qualidade** (low/medium/high)
4. **Clique "Comprimir"** e aguarde
5. **Descarregue** os ficheiros em ZIP

## Estrutura Docker Detalhes

- **Base:** `php:8.3-apache-bookworm`
- **Dependências:** Ghostscript, libzip, curl, unzip
- **Porta:** 80 (mapeada para 8080 no docker-compose)
- **Configuração Apache:** `docker/apache/zz-app.conf` — ativa `AllowOverride All` para o `.htaccess` funcionar (ex.: bloqueio de acesso direto a `/uploads`)
- **Configuração PHP:** `docker/php/conf.d/uploads.ini` — alinhada com `includes/config.php` (uploads grandes, muitos ficheiros): `upload_max_filesize=512M`, `post_max_size=5120M`, `max_file_uploads=1000`, `memory_limit=512M`, `max_execution_time=600`
- **Entrypoint:** `docker/entrypoint.sh` — corrige dono/permissões de `uploads/` a cada arranque antes de iniciar o Apache
- **Health Check:** verifica `/` a cada 30s
- **Volumes:** `/var/www/html/uploads` (persistência)

## Variáveis de Ambiente

Nenhuma variável de ambiente obrigatória. Tudo é configurado em `includes/config.php`.

## Troubleshooting

### Erro: "Ghostscript não detetado"
```bash
# Dentro do contentor
docker-compose exec web apt-get update && apt-get install -y ghostscript
```

### Upload falha ou não comprime
- Verifique permissões da pasta `uploads/` (deve ser escrita por `www-data`) — em deploys via Portainer, confirme que o `entrypoint.sh` correu sem erros nos logs
- Verifique `max_file_bytes` e `max_batch_bytes` em `includes/config.php`
- Verifique espaço disponível em disco

### Contentor não inicia
```bash
# Ver logs
docker-compose logs -f web
```

## Licença

Este projeto está licenciado sob a [Licença MIT](LICENSE). Veja o ficheiro LICENSE para detalhes completos.
