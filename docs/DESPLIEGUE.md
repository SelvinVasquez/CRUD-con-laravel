# Despliegue en Railway

Guía para publicar la API en la nube con HTTPS y dominio propio.

## 1. Subir el código
1. Sube este repositorio a GitHub.
2. En [railway.app](https://railway.app) crea un proyecto con **Deploy from GitHub repo** y elige el repositorio. Railway detecta el `Dockerfile` y construye la imagen.

## 2. Base de datos
1. En el mismo proyecto: **New → Database → MySQL**.
2. En el servicio de Laravel, pestaña **Variables**, referencia los datos de MySQL:
   - `DB_CONNECTION=mysql`
   - `DB_HOST=${{MySQL.MYSQLHOST}}`
   - `DB_PORT=${{MySQL.MYSQLPORT}}`
   - `DB_DATABASE=${{MySQL.MYSQLDATABASE}}`
   - `DB_USERNAME=${{MySQL.MYSQLUSER}}`
   - `DB_PASSWORD=${{MySQL.MYSQLPASSWORD}}`

## 3. Variables de la aplicación
Usa `.env.production.example` como guía. Las imprescindibles:

| Variable | Valor |
|---|---|
| `APP_ENV` | `production` |
| `APP_DEBUG` | `false` |
| `APP_KEY` | resultado de `php artisan key:generate --show` |
| `APP_URL` | `https://api.tudominio.com` |
| `CORS_ALLOWED_ORIGINS` | dominio de la interfaz web |

## 4. Claves de Passport
Las claves no se suben a git. Genéralas en tu PC con `php artisan passport:keys` y copia el contenido de `storage/oauth-private.key` y `storage/oauth-public.key` a las variables `PASSPORT_PRIVATE_KEY` y `PASSPORT_PUBLIC_KEY` (respetando los saltos de línea).

## 5. Cliente de tokens personales
La primera vez, desde la pestaña de shell del servicio o con la CLI de Railway:

```
php artisan passport:client --personal --name="CineTeca" --provider=users
```

Sin este cliente, `/api/login` no puede emitir tokens.

## 6. Dominio y HTTPS
1. En el servicio: **Settings → Networking → Custom Domain** y escribe `api.tudominio.com`.
2. Crea el registro `CNAME` que Railway indica en el proveedor de tu dominio.
3. Railway emite el certificado TLS automáticamente. Verifica con `https://api.tudominio.com/up`.

## 7. Probar
```
curl -X POST https://api.tudominio.com/api/login \
  -H "Accept: application/json" -H "Content-Type: application/json" \
  -d '{"email":"...","password":"..."}'
```
