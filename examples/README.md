# Examples

```sh
composer install
export VEILLE_API_KEY="YOUR_API_KEY"
php examples/json.php
```

- `json.php` — call the featured endpoint and print the JSON result.
- `request.php` — call any path with `request()` and handle API errors.
- `wait-for-job.php` — start an async job and wait for its result.
