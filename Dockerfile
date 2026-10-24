# Chạy Pinpoint trên dịch vụ đám mây hỗ trợ Docker (ví dụ Render). Khi chạy trên máy cá nhân không cần tệp này.
FROM php:8.3-cli
RUN apt-get update \
 && apt-get install -y --no-install-recommends libpq-dev libpng-dev libjpeg62-turbo-dev \
 && docker-php-ext-configure gd --with-jpeg \
 && docker-php-ext-install pgsql gd \
 && rm -rf /var/lib/apt/lists/*
WORKDIR /app
COPY Ma_nguon/ /app/
RUN rm -rf /app/.local /app/node_modules /app/storage/uploads/* && mkdir -p /app/storage/uploads
ENV PORT=10000
EXPOSE 10000
CMD ["sh", "/app/tools/docker-start.sh"]
