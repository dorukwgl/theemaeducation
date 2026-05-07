# EMA Education Platform — Development Guide

PHP 8.0+ educational management system (quiz/exams, files, access control). Custom lightweight framework, PSR-4 autoloading, `EMA\*` namespace.

## Architecture

| Layer | Directory | Key Files |
|-------|-----------|-----------|
| Config | `src/config/` | `config.php`, `database.php`, `constants.php` |
| Core | `src/core/` | `App.php`, `Router.php`, `Request.php`, `Response.php` |
| Middleware | `src/middleware/` | `AuthMiddleware`, `CorsMiddleware`, `RateLimitMiddleware`, `ValidationMiddleware` |
| Controllers | `src/controllers/` | `Auth`, `User`, `Admin`, `Quiz`, `File`, `Folder`, `Notice`, `Access`, `System`, `Csrf` |
| Models | `src/models/` | `User`, `QuizSet`, `Question`, `File`, `Folder`, `Notice`, `Access`, `AdminDashboard` |
| Services | `src/services/` | `AuthService`, `AccessService`, `QuizService`, `NoticeService`, `SystemMonitoringService` |
| Utils | `src/utils/` | `Logger`, `Security`, `Validator`, `Pagination`, `ImageProcessor` |
| Entry | `public/index.php` | Route definitions |
| DB | `database/migrations/` | Schema migrations |

## Patterns

### Auth
- Session-based, **120 min** inactivity timeout (config: `session_lifetime`)
- Admin guard: `new AuthMiddleware([Constants::ROLE_ADMIN])` — regular: `AuthMiddleware::class`
- CSRF tokens required on all POST/PUT/DELETE (`Security::generateCsrfToken()` / `verifyCsrfToken()`)

### DB
- `Database::prepare()` + `bind_param()` for all queries (no raw SQL)
- `Database::query()` only for simple param-less queries
- Transactions: `beginTransaction()` / `commit()` / `rollback()`

### Response format
```json
{"success": true,  "message": "...", "data": {}}
{"success": false, "message": "...", "errors": []}
```

### Controller template
```php
class XController {
    public function method(): void {
        try {
            $user = AuthMiddleware::getCurrentUser();
            // logic
            (new Response)->success('ok', $data);
        } catch (\Exception $e) {
            Logger::error('msg', ['error' => $e->getMessage()]);
            (new Response)->error('msg', 500);
        }
    }
}
```

### Model template
```php
class X {
    public static function findById(int $id): ?array {
        $stmt = Database::prepare("SELECT * FROM table WHERE id = ?");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $result = $stmt->get_result();
        $stmt->close();
        return $result->num_rows > 0 ? $result->fetch_assoc() : null;
    }
}
```

### Service template
```php
class XService {
    public function process(array $data): array {
        $v = Validator::make($data, ['field' => 'required|email']);
        if (!$v->validate()) return ['success' => false, 'errors' => $v->getErrors()];
        return ['success' => true, 'data' => $result];
    }
}
```

## Security rules
- `Validator::make()` on all user input
- `Security::hashPassword()` / `verifyPassword()` for passwords
- File uploads: validate MIME, size, extension; secure filenames
- `Logger::securityEvent()` for audit logging
- Rate limiting on sensitive endpoints
- HTTP codes: 200, 201, 400, 401, 403, 404, 422, 500

## Commands
```
composer install          # deps
composer dump-autoload    # regenerate autoloader
composer test             # phpunit
composer migrate          # run migrations
php -l path/to/file.php   # syntax check
```

## Codebase tooling

Two complementary tools for codebase understanding:

### Graphify (AST knowledge graph)
Knowledge graph at `graphify-out/`. Use for high-level structural queries and cross-module relationships.

**Always use `graphify query "..."` to answer codebase questions before reading files directly.** Querying the graph is faster and cheaper than raw file reads.

- Only read files directly when `graphify query` can't find what you need
- For orientation: read `graphify-out/GRAPH_REPORT.md` first (god nodes & community)
- **Run `graphify update .` after every change** — AST-only, no API cost, not optional

### context-mode (semantic indexing)
**Always use context-mode on any output exceeding ~20 lines** (logs, API responses, test output, git diffs, search results, large file reads). It handles efficient processing and summarization of large data.

### When to use which
| Scenario | Tool |
|---|---|
| Architecture questions, cross-module relationships | graphify query |
| Large output processing, log analysis, diff review | context-mode |
| Graphify unavailable or insufficient | context-mode (always available) |
| After every code change | graphify update |

**Always use both where applicable.** context-mode is the universal fallback — always available, always on.