# Despliegue en Google Cloud Run + Supabase

## 1. Supabase (base de datos)
1. En el proyecto de Supabase: **Connect → Session pooler** y copia host, usuario (`postgres.<ref>`) y puerto 5432.
2. La contraseña es la de la base de datos (Project Settings → Database).

## 2. Claves de Passport
En tu PC: `php artisan passport:keys`. El contenido de `storage/oauth-private.key` y `storage/oauth-public.key` va a Secret Manager (no a git).

## 3. Secretos y despliegue
```bash
gcloud config set project TU_PROYECTO
gcloud services enable run.googleapis.com cloudbuild.googleapis.com secretmanager.googleapis.com artifactregistry.googleapis.com

php artisan key:show   # o: php artisan key:generate --show
printf '%s' 'base64:...' | gcloud secrets create app-key --data-file=-
printf '%s' 'PASSWORD_SUPABASE' | gcloud secrets create db-password --data-file=-
gcloud secrets create passport-private --data-file=storage/oauth-private.key
gcloud secrets create passport-public --data-file=storage/oauth-public.key

gcloud run deploy cineteca-api --source . --region us-central1 --allow-unauthenticated \
  --set-env-vars APP_ENV=production,APP_DEBUG=false,LOG_CHANNEL=stderr,SESSION_DRIVER=array,CACHE_STORE=array,QUEUE_CONNECTION=sync,DB_CONNECTION=pgsql,DB_HOST=aws-0-REGION.pooler.supabase.com,DB_PORT=5432,DB_DATABASE=postgres,DB_USERNAME=postgres.REF,DB_SSLMODE=require \
  --set-secrets APP_KEY=app-key:latest,DB_PASSWORD=db-password:latest,PASSPORT_PRIVATE_KEY=passport-private:latest,PASSPORT_PUBLIC_KEY=passport-public:latest
```
Si Cloud Run no puede leer los secretos, da el rol *Secret Manager Secret Accessor* a su cuenta de servicio.

## 4. Cliente personal de Passport (una sola vez)
La migración corre al arrancar; después crea el cliente apuntando a Supabase desde tu PC (con el `.env` local en `pgsql`):
```bash
php artisan passport:client --personal --name="CineTeca" --provider=users
```

## 5. Verificar y conectar la app
`curl https://<servicio>.run.app/up` debe responder 200. Luego en la app: `EXPO_PUBLIC_API_URL=https://<servicio>.run.app/api`.
