# API de CineTeca — guía para integrarla

**Base URL:** `https://cineteca-api-12771576319.us-central1.run.app/api`

API REST en JSON (Laravel + Passport). Maneja usuarios y su catálogo personal de películas.
Cada usuario solo ve y modifica **sus propias** películas.

## Reglas generales

- Envía siempre los headers `Content-Type: application/json` y `Accept: application/json`.
- Las rutas protegidas necesitan `Authorization: Bearer <token>`.
- Todas las respuestas tienen la forma:
  ```json
  { "success": true, "message": "…", "data": … }
  ```
- Si falla: `success: false`, `message` y, en validaciones (422), un objeto `errors`.
- CORS abierto (`*`): se puede llamar desde una web en el navegador.
- `/register` y `/login` permiten **5 intentos por minuto** por IP (si pasas el límite: 429).

## 1. Autenticación

### Registrar usuario — `POST /register`
```json
{ "name": "Ana", "email": "ana@example.com", "password": "secreto123" }
```
Respuesta `201`: `data` es el usuario creado. La contraseña requiere mínimo 6 caracteres.

### Iniciar sesión — `POST /login`
```json
{ "email": "ana@example.com", "password": "secreto123" }
```
Respuesta `200`:
```json
{
  "success": true,
  "message": "Inicio de sesión correcto",
  "data": { "user": { "id": 1, "name": "Ana", "email": "ana@example.com" }, "token": "eyJ0eXAiOi..." }
}
```
Guarda `data.token` y envíalo en cada petición protegida.
Opcional: `"scopes": ["peliculas.read"]` para pedir un token con permisos limitados
(`peliculas.read`, `peliculas.write`, `peliculas.delete`). Sin `scopes` tiene todos.

Credenciales incorrectas: `401`.

### Usuario actual — `GET /user` 🔒
### Cerrar sesión — `POST /logout` 🔒 (revoca el token)

## 2. Películas 🔒

| Método | Ruta | Scope | Qué hace |
|---|---|---|---|
| GET | `/peliculas` | `peliculas.read` | Lista tus películas |
| GET | `/peliculas/{id}` | `peliculas.read` | Una película |
| POST | `/peliculas` | `peliculas.write` | Crea una película |
| PUT | `/peliculas/{id}` | `peliculas.write` | Actualiza una película |
| DELETE | `/peliculas/{id}` | `peliculas.delete` | Elimina una película |

### Campos de una película

| Campo | Tipo | Requerido | Notas |
|---|---|---|---|
| `titulo` | string (≤255) | sí | |
| `anio` | entero (≥1888) | sí | Año de estreno (sin "ñ") |
| `genero` | string (≤255) | sí | |
| `sinopsis` | string | no | |
| `poster_url` | string (≤2048) | no | URL de la imagen |
| `calificacion` | número 0–10 | no | |
| `favorita` | booleano | no | |
| `vista` | booleano | no | |

No se permiten dos películas con el mismo título y año para el mismo usuario (`422`).

### Ejemplo — crear
```bash
curl -X POST https://cineteca-api-12771576319.us-central1.run.app/api/peliculas \
  -H "Content-Type: application/json" -H "Accept: application/json" \
  -H "Authorization: Bearer TU_TOKEN" \
  -d '{"titulo":"Interestelar","anio":2014,"genero":"Ciencia ficción","calificacion":8.6}'
```

### Ejemplo — listar
```bash
curl https://cineteca-api-12771576319.us-central1.run.app/api/peliculas \
  -H "Accept: application/json" -H "Authorization: Bearer TU_TOKEN"
```

### Ejemplo — JavaScript
```js
const API = 'https://cineteca-api-12771576319.us-central1.run.app/api';

const login = await fetch(`${API}/login`, {
  method: 'POST',
  headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
  body: JSON.stringify({ email: 'ana@example.com', password: 'secreto123' }),
}).then((r) => r.json());

const token = login.data.token;

const peliculas = await fetch(`${API}/peliculas`, {
  headers: { Accept: 'application/json', Authorization: `Bearer ${token}` },
}).then((r) => r.json());
console.log(peliculas.data);
```

## Códigos de respuesta

| Código | Significado |
|---|---|
| 200 / 201 | OK / creado |
| 401 | Falta token, token inválido o credenciales incorrectas |
| 403 | El token no tiene el scope necesario |
| 404 | Película no encontrada |
| 422 | Error de validación (ver `errors`) |
| 429 | Demasiados intentos de login/registro |

## Probar sin código

Importa `peliculas-api.postman_collection.json` en Postman y cambia la variable
`base_url` por la Base URL de arriba.

---

# API pública del grupo (sin login)

Pensada para que los compañeros consuman el catálogo **sin registrarse ni usar token**.
Prefijo: `/publico`. Las respuestas tienen la misma forma `{ success, message, data }`.
Las escrituras (POST) tienen un límite de **30 peticiones por minuto**.

| Método | Ruta | Para quién | Qué hace |
|---|---|---|---|
| GET | `/publico/peliculas` | Lester | Listado general de **todas** las películas. Filtros: `?q=texto&categoria_id=1&anio=2014&per_page=50` |
| GET | `/publico/peliculas/{id}` | todos | Detalle de una película |
| POST | `/publico/peliculas` | Lester | Registrar una película |
| GET | `/publico/categorias` | Lester | Listado de categorías (con `peliculas_count`) |
| POST | `/publico/categorias` | Lester | Crear una categoría `{ "nombre": "Acción" }` |
| GET | `/publico/peliculas/ranking?orden=valoradas` | Jose | Películas mejor valoradas (`limit`, por defecto 10) |
| GET | `/publico/peliculas/ranking?orden=vistas` | Jose | Películas con más visualizaciones |
| POST | `/publico/peliculas/{id}/reviews` | Jose | Registrar una review |
| GET | `/publico/peliculas/{id}/reviews` | Jose | Listar las reviews de una película |
| POST | `/publico/peliculas/{id}/visualizar` | todos | Suma una visualización |

### Registrar película — `POST /publico/peliculas`
```json
{ "titulo": "Interestelar", "anio": 2014, "genero": "Ciencia ficción",
  "categoria_id": 1, "sinopsis": "…", "poster_url": "https://…", "calificacion": 8.6 }
```
Requeridos: `titulo`, `anio`, `genero`. `categoria_id` debe existir (créala antes con `POST /publico/categorias`).
Respuesta `201` con la película, su `categoria`, `reviews_count` y `reviews_avg_calificacion`.
Si ya existe el mismo título y año: `422`.

### Registrar review — `POST /publico/peliculas/{id}/reviews`
```json
{ "autor": "Jose", "calificacion": 8, "comentario": "Muy buena" }
```
`autor` y `calificacion` (entero 1–10) son requeridos; `comentario` es opcional.

### Ejemplo de película (campos que devuelve)
```json
{
  "id": 12, "titulo": "Interestelar", "sinopsis": "…", "anio": 2014,
  "genero": "Ciencia ficción", "categoria_id": 1,
  "categoria": { "id": 1, "nombre": "Ciencia ficción" },
  "poster_url": "https://…", "calificacion": "8.6", "visualizaciones": 15,
  "reviews_count": 2, "reviews_avg_calificacion": 7.0
}
```
El listado `/publico/peliculas` viene paginado: las películas están en `data.data`,
y el total y las páginas en `data.total` / `data.last_page`. Usa `?page=2` para la siguiente.

### Ejemplos rápidos
```bash
BASE=https://cineteca-api-12771576319.us-central1.run.app/api

# Listado general
curl "$BASE/publico/peliculas" -H "Accept: application/json"

# Más valoradas
curl "$BASE/publico/peliculas/ranking?orden=valoradas&limit=5" -H "Accept: application/json"

# Registrar una review
curl -X POST "$BASE/publico/peliculas/1/reviews" \
  -H "Content-Type: application/json" -H "Accept: application/json" \
  -d '{"autor":"Jose","calificacion":9,"comentario":"Excelente"}'
```
