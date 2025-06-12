php -S localhost:8000 -t public
runserver

npx tailwindcss -i ./public/input.css -o ./public/output.css --watch  
run tailwind๋

## Docker build and update
docker-compose down
docker-compose up -d --build
