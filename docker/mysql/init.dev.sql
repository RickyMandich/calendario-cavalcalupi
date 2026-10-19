CREATE USER IF NOT EXISTS 'calendario_cavalcalupi'@'172.25.0.%' IDENTIFIED BY '';
GRANT ALL PRIVILEGES ON calendario_cavalcalupi.* TO 'calendario_cavalcalupi'@'172.25.0.%';
FLUSH PRIVILEGES;
