FROM php:8.2-apache

# ส่วนขยาย PHP ที่โปรเจกต์ใช้: pdo_mysql (ฐานข้อมูล), zip (ดาวน์โหลดรายงานเป็น .zip ในหน้าแอดมิน)
RUN apt-get update \
    && apt-get install -y --no-install-recommends libzip-dev zip unzip \
    && docker-php-ext-install pdo_mysql zip \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# เปิด mod_rewrite และให้ .htaccess มีผล (ค่าเริ่มต้นของ image คือ AllowOverride None)
RUN a2enmod rewrite \
    && sed -ri 's!AllowOverride None!AllowOverride All!g' /etc/apache2/apache2.conf

COPY php.ini /usr/local/etc/php/conf.d/zz-app.ini

# โค้ดถูก mount เข้ามาตอนรัน (ดู docker-compose.yml) จึงไม่ต้อง COPY
