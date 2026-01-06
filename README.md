#Speed Test Dashboard

Multi-server LibreSpeed implementation with custom analytics dashboard for network performance monitoring.

## Features

- 🚀 Multi-server speed testing (Gurgaon, Bangalore, Mumbai, Chennai)
- 📊 Real-time network metrics (Download, Upload, Ping, Jitter, Packet Loss, Latency)
- 🎨 Modern dark-themed UI with Spectra branding
- 📈 Comprehensive analytics dashboard
- 🔍 Advanced filtering (Date, IP, Server location)
- 📥 CSV export functionality
- 🔐 Password-protected admin panel
- 🌐 SOAP API integration for customer data enrichment

## Tech Stack

- **Frontend:** HTML5, CSS3, JavaScript
- **Backend:** PHP 7.4+
- **Database:** MySQL 5.7+
- **Server:** Nginx/Apache
- **Speed Test Engine:** LibreSpeed

## Installation

### 1. Clone the Repository
```bash
git clone https://github.com/adityarajshekhawat/speedtest
cd speedtest
```

### 2. Database Setup
```bash
mysql -u root -p
```
```sql
CREATE DATABASE speedtest;
CREATE USER 'speedtest_user'@'localhost' IDENTIFIED BY 'your_password';
GRANT ALL PRIVILEGES ON speedtest.* TO 'speedtest_user'@'localhost';
FLUSH PRIVILEGES;
```

Import the schema:
```bash
mysql -u speedtest_user -p speedtest < database/schema.sql
```

### 3. Configure Settings
```bash
cp results/telemetry_settings.example.php results/telemetry_settings.php
nano results/telemetry_settings.php
```

Update with your database credentials and admin password.

### 4. Web Server Configuration

**Nginx Example:**
```nginx
server {
    listen 80;
    server_name speedtest.example.com;
    root /var/www/html/librespeed;
    index index.html;

    location / {
        try_files $uri $uri/ =404;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php-fpm/php-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }
}
```

### 5. Set Permissions
```bash
chown -R www-data:www-data /var/www/html/librespeed
chmod 755 /var/www/html/librespeed
chmod 644 results/telemetry_settings.php
```

## Database Schema

The system stores comprehensive test results with 26 fields:

- Basic metrics: Download, Upload, Ping, Jitter
- Custom metrics: Packet Loss, Latency
- Customer data: Account Name, Service ID, MAC Address
- Location data: City, Address, Controller
- API status tracking

## API Integration

The system integrates with SOAP APIs to enrich test data with customer information:

- Fetches customer details by IP address
- Runs as a background cron job
- Updates records with service plans, bandwidth policies, and location data

## Usage

### Running Speed Tests

1. Navigate to `http://your-domain.com`
2. Select server location (Gurgaon/Bangalore/Mumbai/Chennai)
3. Click "Start" to begin test
4. Results are automatically saved to database

### Viewing Analytics

1. Go to `http://your-domain.com/results/stats.php`
2. Enter admin password
3. Filter by date, IP, or server location
4. Export data to CSV

## Project Structure
```
librespeed/
├── index.html              # Main speed test interface
├── results/
│   ├── stats.php          # Analytics dashboard
│   ├── export.php         # CSV export handler
│   ├── telemetry.php      # Data collection endpoint
│   └── telemetry_settings.example.php
├── backend/               # LibreSpeed backend files
├── favicon.png
├── Spectra-Regular.ttf
└── banner-element.png
```


## Contributing

1. Fork the repository
2. Create your feature branch (`git checkout -b feature/AmazingFeature`)
3. Commit your changes (`git commit -m 'Add some AmazingFeature'`)
4. Push to the branch (`git push origin feature/AmazingFeature`)
5. Open a Pull Request

## License

This project is licensed under the MIT License - see the LICENSE file for details.

## Acknowledgments

- LibreSpeed for the speed test engine
- Spectra Telecommunications for branding and requirements

## Support

For issues and questions, please open an issue on GitHub.