# Backup e atualização

Antes de cada atualização, exporte o banco pelo phpMyAdmin e faça uma cópia de `storage/uploads`.

1. No Git, atualize a branch `main`.
2. Faça backup do banco e dos uploads.
3. Atualize os arquivos da aplicação.
4. Acesse uma rota autenticada para provisionar migrações pendentes.
5. Teste login, catálogo, aula, upload e painel administrativo.
6. Mantenha o backup anterior até a validação final.

Em hospedagem compartilhada, recomenda-se criar um backup diário pelo recurso **Backup** do cPanel e manter ao menos sete versões. Nunca exponha `config/config.php`, `database/` ou `storage/` publicamente.
