# Publicar una versión

Lo que se distribuye son los **fuentes**: `assets/build/` está en `.gitignore` a
propósito, porque es el tema quien compila.

```bash
bun run typecheck                   # tipos
composer lint                       # estándares de código
git tag -a v0.2.0 -m "Forja 0.2.0"
git push origin main --tags
```

Para consumirlo sin Packagist, basta con declarar el repositorio en el tema:

```json
{
    "repositories": [
        { "type": "vcs", "url": "git@github.com:oswa/forja.git" }
    ],
    "require": { "oswa/forja": "^0.2" }
}
```
