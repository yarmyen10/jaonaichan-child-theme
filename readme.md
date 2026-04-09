```bash
node -v # version 24.14.1

npx @tailwindcss/cli -i ./src/input.css -o ./assets/css/tailwind.css --watch

#
for f in ./src/inc/i18n/languages/jaonaichan-*.po; do msgfmt "$f" -o "${f%.po}.mo"; done

```