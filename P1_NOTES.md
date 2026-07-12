# P1 Wave — Notes

## Browser tests — come si eseguono

I test end-to-end vivono in `tests/Browser/` e girano con **Pest 4 browser testing**
(`pestphp/pest-plugin-browser` + Playwright), non con Dusk. Il plugin avvia un
server HTTP embedded in-process e vi punta un browser headless (Chromium via
Playwright), condividendo la transazione `RefreshDatabase` con il test.

### Prerequisiti (una tantum)

- **Browser Playwright installati nel container Sail** (i test girano lì):
  ```bash
  vendor/bin/sail exec laravel.test npx playwright install chromium
  ```
  È già incluso in `composer setup` (`npx playwright install chromium`).

### Esecuzione

- **Tutta la suite** (Unit + Feature + Browser): `vendor/bin/sail composer test`
- **Solo i browser test**: `vendor/bin/sail composer test:browser`
- **Coverage** (esclude i Browser, che non incidono sulla soglia):
  `vendor/bin/sail exec -T -e XDEBUG_MODE=coverage laravel.test composer test:coverage`

### Nota importante: build prima dei browser test

I browser test caricano gli **asset buildati** (`public/build`), non il Vite dev
server. Dopo aver modificato file `.vue`/`.ts` rigenera Wayfinder e ribuilda prima
di eseguirli:

```bash
vendor/bin/sail artisan wayfinder:generate --with-form
npm run build:assets
```

Il pre-push hook builda già prima dei test; la CI ha un job `browser` dedicato che
installa Playwright (`npx playwright install --with-deps chromium`) e builda gli
asset prima di eseguire `composer test:browser`.
