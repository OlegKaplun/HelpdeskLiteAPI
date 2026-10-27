# Helpdesk Lite API

HTTP API внутреннего отдела поддержки (http://localhost:8080/api/documentation)

**Стек:** PHP 8.2, Laravel 12, MySQL 8.0, Nginx, Swagger (чтобы не прописывать curl вручную, решил написать простенькую документацию).
**Инфраструктура:** Docker Compose (app + nginx + db).

---

## Разворачивание проекта

```bash
# 1. Клонировать репозиторий и перейти в директорию
cd HelpdeskLiteAPI

# 2. Отредактировать docker/env/mysql.env (По хорошему этот файл добавляется в .gitignore, но не стал этого делать в тестовом задании)
# Данный файл можно не редактировать и использовать текущие значения 

# 3. Подготовить src/.env ()
cp src/.env.example src/.env
# отредактировать src/.env — DB_HOST=db (Раскомментировать, но не менять!!!, т.к. используется docker), скопировать остальные значения из mysql.env
# отредактировать APP_API_KEY на свой

# 4. Поднять контейнеры
docker compose up -d --build

# 5. Инициализировать Laravel
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate --force

# 6. Сгенерировать Swagger
docker compose exec app php artisan l5-swagger:generate

# Для остановки контейнеров использовать:
docker compose down
```