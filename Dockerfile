# Stage 1: Build environment and Composer dependencies
FROM php:8.4-fpm AS builder

# Install system dependencies and PHP extensions required for Laravel + MySQL support
# Some dependencies are required for PHP extensions only in the build stage
RUN apt-get update && apt-get install -y --no-install-recommends \
    curl \
    git \
    unzip \
    vim \
    wget \
    libpng-dev \
    libonig-dev \
    libssl-dev \
    libxml2-dev \
    libcurl4-openssl-dev \
    libicu-dev \
    libzip-dev \
    libfcgi-bin \
    procps

RUN docker-php-ext-install -j$(nproc) \
    pdo_mysql \
    mbstring \
    exif \
    pcntl \
    bcmath \
    gd \
    zip \
    intl \
    opcache

RUN pecl install redis && \
    pecl install xdebug && \
    docker-php-ext-enable redis xdebug

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Install Node.js LTS
RUN curl -fsSL https://deb.nodesource.com/setup_24.x | bash - \
    && apt-get install -y nodejs \
    && apt-get clean

# Install Bun
ENV BUN_INSTALL="/opt/bun"
RUN curl -fsSL https://bun.sh/install | bash
ENV PATH="${BUN_INSTALL}/bin:${PATH}"

# Clean up
RUN apt-get autoremove -y && apt-get clean && rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/*



# Stage 2: Development image
FROM builder AS development

# Set the working directory
WORKDIR /var/www

# Create non-root user for Laravel
RUN groupadd -g 1000 laravel \
    && useradd -u 1000 -g laravel -m laravel

# Set proper permissions
RUN chown -R laravel:laravel /var/www

# Copy entrypoint script
COPY docker-entrypoint.sh /usr/local/bin/
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

# Switch to non-root user
USER laravel

# Install Claude Code
RUN curl -fsSL https://claude.ai/install.sh | bash
ENV PATH="/home/laravel/.local/bin:${PATH}"

RUN echo 'alias ll="ls -alh"' >> ~/.bashrc && \
    echo 'alias cld="claude"' >> ~/.bashrc && \
    echo 'alias cldc="claude --continue"' >> ~/.bashrc && \
    echo 'alias cldyolo="claude --dangerously-skip-permissions"' >> ~/.bashrc && \
    echo 'alias cldcyolo="claude --continue --dangerously-skip-permissions"' >> ~/.bashrc 

EXPOSE 8000 5173

CMD ["bash"]