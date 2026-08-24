# [PDF Sucker](https://pdf.entr0py.cc/) 🌬️

Aplicação web para compressão de ficheiros PDF no servidor. Reduz o tamanho dos PDFs mantendo a qualidade usando [Ghostscript](https://www.ghostscript.com/).

**Desenvolvido por:** [João](https://github.com/JoaoTom1922) e [Miguel](https://github.com/miguelthemann) (14/05/2026)

## Recursos

- ✅ **Upload múltiplo** — até 1000 ficheiros por requisição
- ✅ **Compressão em servidor** — usa Ghostscript para reduzir tamanho
- ✅ **3 níveis de qualidade** — escolha entre qualidade vs tamanho
- ✅ **Interface web moderna** — tema responsivo em vermelho e preto
- ✅ **Limpeza automática** — ficheiros deletados após 30 minutos
- ✅ **Download direto** — descarregue PDFs comprimidos em ZIP
- ✅ **Containerizado** — deploy fácil com Docker

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

### Método 1: Docker Compose (Mais fácil)

```bash
# Clone ou aceda ao repositório
cd pdf-sucker

# Inicie o serviço
docker-compose up -d

# Aceda em http://localhost:8080
```

**O que acontece:**
- A imagem é descarregada do registry GHCR (ghcr.io/miguelthemann/pdf-sucker:latest)
- Apache é iniciado na porta 8080
- Ghostscript está pré-instalado
- Os uploads são persistidos em volume Docker

### Método 2: Docker direto

```bash
# Descarregar imagem
docker pull ghcr.io/miguelthemann/pdf-sucker:latest

# Executar contentor
docker run -d \
  --name pdf-sucker \
  -p 8080:80 \
  -v pdf-uploads:/var/www/html/uploads \
  ghcr.io/miguelthemann/pdf-sucker:latest

# Aceda em http://localhost:8080
```

### Parar o serviço

```bash
# Se usou Docker Compose
docker-compose down

# Se usou Docker direto
docker stop pdf-sucker
docker rm pdf-sucker
```

## Configuração

Edite `includes/config.php` para personalizar:

```php
// Tamanho máximo por ficheiro (padrão: 50 MB)
'max_file_bytes' => 50 * 1024 * 1024,

// Tempo de expiração dos ficheiros (padrão: 30 minutos)
'ttl_minutes' => 30,

// Máximo de ficheiros por upload (padrão: 1000)
'max_files_per_upload' => 1000,

// Níveis de compressão PDF
'pdf_settings' => [
    'low' => '/printer',   // Baixa compressão, melhor qualidade
    'medium' => '/ebook',  // Balanço
    'high' => '/screen',   // Máxima compressão
],
```

## Utilização

1. **Abra** http://localhost:8080
2. **Arraste ficheiros PDF** ou clique para selecionar
3. **Escolha o nível de qualidade** (low/medium/high)
4. **Clique "Comprimir"** e aguarde
5. **Descarregue** os ficheiros em ZIP

## Dockerfile Detalhes

- **Base:** `php:8.3-apache-bookworm`
- **Dependências:** Ghostscript, libzip, curl
- **Porta:** 80 (mapeada para 8080 no docker-compose)
- **Health Check:** Verifica /index.php a cada 30s
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
- Verifique permissões da pasta `uploads/` (deve ser escrita por `www-data`)
- Verifique `max_file_bytes` em config.php
- Verifique espaço disponível em disco

### Contentor não inicia
```bash
# Ver logs
docker-compose logs -f web
```

## Licença

Este projeto está licenciado sob a [Licença MIT](LICENSE). Veja o ficheiro LICENSE para detalhes completos.
