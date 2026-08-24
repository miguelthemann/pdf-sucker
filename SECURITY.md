# Security Policy

## Supported Versions

Apenas a versão mais recente do PDF Sucker é suportada ativamente com atualizações de segurança.

| Version | Supported          |
| ------- | ------------------ |
| latest  | :white_check_mark: |
| < latest| :x:                |

## Reporting a Vulnerability

Se descobrires uma vulnerabilidade de segurança no PDF Sucker, por favor **não** cries uma issue pública. Isso pode expor a vulnerabilidade antes de podermos corrigi-la.

### Como reportar

1. **Enviar por email privado:**
   - Contacta os maintainers diretamente via GitHub (secção Discussions ou através dos perfis)
   - Ou abre uma **Security Advisory** no GitHub: `Security` tab → `Report a vulnerability`

2. **Informação a incluir:**
   - Descrição detalhada da vulnerabilidade
   - Passos para reproduzir o problema
   - Versão afetada (tag, commit hash, ou `latest`)
   - Impacto potencial (que dados/sistemas estão em risco)
   - Sugestões de correção (se tiveres)

### O que esperar

- **Resposta inicial:** Dentro de 48 horas
- **Avaliação:** Confirmaremos se a vulnerabilidade é válida
- **Correção:** Trabalharemos numa patch e lançaremos uma atualização
- **Divulgação:** Após a correção estar disponível, publicaremos detalhes da vulnerabilidade corrigida

## Security Best Practices

Para utilizadores do PDF Sucker, recomendamos:

### Deploy Seguro

- **Nunca exponhas diretamente à internet** sem autenticação/firewall
- **Usa HTTPS** (reverse proxy como Caddy/Nginx com TLS)
- **Limita upload sizes** em `includes/config.php` conforme necessário
- **Isola o contentor** (Docker network isolation, não usar `--privileged`)

### Manutenção

- **Atualiza regularmente** para a versão `latest` do GHCR
- **Monitoriza logs** do Apache para atividade suspeita
- **Limpa ficheiros temporários** (o sistema faz isto automaticamente, mas verifica)

### File Upload Safety

- O PDF Sucker **apenas aceita ficheiros PDF** (validação de MIME type)
- Ghostscript processa ficheiros em ambiente isolado
- Ficheiros temporários são **eliminados após 30 minutos** (configurável)

### Known Limitations

- **Path Traversal:** Upload paths são sanitizados, mas sempre valida permissões
- **DoS via large files:** Limite de 50 MB por ficheiro (configurável)
- **Ghostscript exploits:** Mantém Ghostscript atualizado no contentor base

## Scope

Vulnerabilidades **em scope**:
- Remote Code Execution (RCE)
- Path Traversal / Directory Traversal
- File Upload bypass (executar ficheiros maliciosos)
- SQL Injection (se houver BD no futuro)
- Cross-Site Scripting (XSS) persistente
- Authentication bypass (se houver auth no futuro)
- Denial of Service (DoS) exploitável

Vulnerabilidades **fora de scope**:
- Issues de UI/UX sem impacto de segurança
- Rate limiting (não implementado por design)
- Configurações inseguras do utilizador (exposição pública sem TLS é responsabilidade do utilizador)

## Credits

Agradecemos a todos que reportarem vulnerabilidades de forma responsável. Os teus contributos tornam o PDF Sucker mais seguro para todos.

---

**Desenvolvido por [João](https://github.com/JoaoTom1922) e [Miguel](https://github.com/miguelthemann)** 
