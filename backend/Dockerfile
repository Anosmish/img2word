# Use the official PHP Apache image with specific version for stability
FROM php:8.1-apache-bullseye

# Set timezone to avoid interactive prompts during build
ENV TZ=UTC
RUN ln -snf /usr/share/zoneinfo/$TZ /etc/localtime && echo $TZ > /etc/timezone

# Update package lists and install basic dependencies first
RUN apt-get update && apt-get install -y --no-install-recommends \
    ca-certificates \
    gnupg \
    wget

# Add stable repository for tesseract
RUN echo "deb http://deb.debian.org/debian bullseye main" > /etc/apt/sources.list && \
    echo "deb http://deb.debian.org/debian bullseye-updates main" >> /etc/apt/sources.list && \
    echo "deb http://security.debian.org/debian-security bullseye-security main" >> /etc/apt/sources.list

# Install system dependencies in smaller chunks
RUN apt-get update && apt-get install -y --no-install-recommends \
    libfreetype6-dev \
    libjpeg62-turbo-dev \
    libpng-dev \
    libwebp-dev

RUN apt-get install -y --no-install-recommends \
    git \
    unzip

# Install Tesseract OCR with minimal language packs first
RUN apt-get update && apt-get install -y --no-install-recommends \
    tesseract-ocr

# Install additional language packs one by one to avoid conflicts
RUN apt-get update && apt-get install -y --no-install-recommends \
    tesseract-ocr-eng \
    tesseract-ocr-spa \
    tesseract-ocr-fra

RUN apt-get update && apt-get install -y --no-install-recommends \
    tesseract-ocr-deu \
    tesseract-ocr-ita \
    tesseract-ocr-por

RUN apt-get update && apt-get install -y --no-install-recommends \
    tesseract-ocr-rus

# Configure and install PHP extensions
RUN docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp && \
    docker-php-ext-install -j$(nproc) gd zip

# Install Composer
COPY --from=composer:2.5 /usr/bin/composer /usr/bin/composer

# Enable Apache mod_rewrite
RUN a2enmod rewrite

# Set the working directory
WORKDIR /var/www/html

# Copy composer files first (for better caching)
COPY composer.json ./

# Install PHP dependencies
RUN composer install --no-dev --optimize-autoloader --no-scripts --no-plugins

# Copy the rest of the application files
COPY . .

# Create necessary directories
RUN mkdir -p uploads output && chmod 755 uploads output

# Change ownership of web directory to Apache user
RUN chown -R www-data:www-data /var/www/html

# Clean up to reduce image size
RUN apt-get clean && \
    rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/*

# Expose port 80
EXPOSE 80

# Health check
HEALTHCHECK --interval=30s --timeout=3s --start-period=5s --retries=3 \
    CMD curl -f http://localhost/ || exit 1

# Start Apache in the foreground
CMD ["apache2-foreground"]
