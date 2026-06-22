# data-light

A standalone AI-powered data analytics platform built with PHP and SQLite.

**Developer:** [Suraj Dubey](https://mrsuraj.rf.gd)  
**GitHub:** [5ur4jd-dev](https://github.com/5ur4jd-dev)

---

## Overview

**data-light** is a lightweight, self-hosted data analytics platform that allows you to upload datasets, perform statistical analysis, and generate AI-powered insights — all without requiring Python, Node.js, Docker, or any external services except PHP and a web server.

### Key Features

- **Dataset Upload**: Support for CSV, XLSX, XLS, and JSON files
- **Statistical Analysis Engine**: Full local analysis in PHP — correlations, distributions, data quality metrics
- **AI-Powered Insights**: Integration with OpenRouter for intelligent business insights
- **Modern Dashboard**: Clean, responsive web interface built with vanilla JavaScript
- **SQLite Persistence**: All data stored locally in a single SQLite file
- **Zero Configuration**: Runs immediately on any PHP server
- **API Key Management**: Configure AI credentials securely through the UI

---

## Requirements

- **PHP 8.1** or higher
- **Composer** (for dependency installation)
- **SQLite** extension enabled in PHP
- **Fileinfo** extension enabled in PHP
- **CURL** extension enabled in PHP (for AI insights)

### Supported Deployment Targets

- Shared Hosting (cPanel, Plesk)
- VPS / Dedicated Server
- XAMPP / WAMP / MAMP
- Local PHP Development Server
- Any server with PHP 8.1+

No Docker. No Node.js. No Python. No build process.

---

## Quick Start

### 1. Install Dependencies

```bash
composer install
```

### 2. Start the Application

```bash
php -S localhost:8000 -t public public/router.php
```

Then open [http://localhost:8000](http://localhost:8000) in your browser.

### 3. Configure AI (Optional)

On first launch, you'll be prompted to enter your OpenRouter API key. You can also configure this later in Settings.

1. Get your free API key from [openrouter.ai](https://openrouter.ai)
2. Enter it in the setup screen or Settings page
3. The default model is `nvidia/nemotron-3-ultra-550b-a55b:free`

---

## Project Structure

```
data-light/
├── public/                  # Web root
│   ├── index.html           # Main SPA entry point
│   └── assets/
│       ├── css/
│       │   └── style.css    # Application styles
│       └── js/
│           └── app.js       # Frontend application
│
├── api/                     # API endpoints
│   ├── index.php            # Health check
│   ├── upload.php           # File upload handler
│   ├── datasets.php         # List datasets
│   ├── dataset.php          # Single dataset
│   ├── analyze.php          # Run analysis
│   ├── analyses.php         # List analyses
│   ├── analysis.php         # Single analysis
│   ├── save-api-key.php     # Save AI credentials
│   ├── api-status.php       # Check AI status
│   ├── delete-api-key.php   # Remove AI credentials
│   ├── delete-dataset.php   # Remove dataset
│   └── export.php           # Export analysis results
│
├── app/                     # Application code
│   ├── Core/
│   │   ├── Database.php     # SQLite database manager
│   │   ├── Response.php     # HTTP response handler
│   │   ├── Validator.php    # Input validation
│   │   └── Env.php          # Environment loader
│   ├── Services/
│   │   ├── DatasetService.php       # Dataset CRUD operations
│   │   ├── AnalysisService.php      # Statistical analysis engine
│   │   ├── OpenRouterService.php    # AI integration
│   │   ├── CsvReader.php            # CSV file parser
│   │   ├── ExcelReader.php          # Excel file parser
│   │   └── JsonReader.php           # JSON file parser
│   └── Helpers/
│       ├── stats.php        # Statistical functions
│       └── files.php        # File handling utilities
│
├── storage/                 # Data storage
│   ├── uploads/             # Uploaded datasets
│   └── data-light.sqlite    # SQLite database
│
├── config/
│   └── config.php           # Application configuration
│
├── composer.json            # PHP dependencies
├── .env.example             # Environment template
└── README.md                # This file
```

---

## API Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/index.php` | Service health check |
| POST | `/api/upload.php` | Upload a dataset |
| GET | `/api/datasets.php` | List all datasets |
| GET | `/api/dataset.php?id={id}` | Get single dataset |
| POST | `/api/analyze.php?id={id}&ai={true/false}` | Run analysis |
| GET | `/api/analyses.php` | List all analyses |
| GET | `/api/analysis.php?id={id}` | Get single analysis |
| POST | `/api/save-api-key.php` | Save OpenRouter credentials |
| GET | `/api/api-status.php` | Check AI configuration status |
| POST | `/api/delete-api-key.php` | Delete AI credentials |
| POST | `/api/delete-dataset.php?id={id}` | Delete a dataset |
| GET | `/api/export.php?id={id}&format=json` | Export analysis as JSON |

---

## Analysis Capabilities

### Statistical Analysis (Local PHP)

- **Overview**: Row count, column count, dataset type classification
- **Data Quality**: Missing values, completeness percentage, duplicate detection
- **Numeric Analysis**: Count, mean, median, standard deviation, min/max, quartiles, outlier detection (IQR method)
- **Correlation Matrix**: Pearson correlation with strong correlation detection (|r| >= 0.5)
- **Categorical Analysis**: Value counts, frequency percentages, top categories
- **Rule-Based Insights**: Automatic detection of data quality issues, correlations, imbalances

### AI Insights (via OpenRouter)

- Generates 3-5 business insights from dataset summaries
- Sends only aggregated statistics — never raw data
- Configurable AI model
- Graceful fallback when AI is unavailable

---

## Security

- MIME type and extension validation on uploads
- File size limits (50MB default)
- Random generated filenames for stored files
- Path traversal prevention
- API keys stored in SQLite, never exposed to frontend
- Masked key display in UI (e.g., `sk-or-v1-****abcd`)
- SQL injection prevention via parameterized queries
- XSS protection via output escaping

---

## Configuration

All application settings are stored in the SQLite database and managed through the UI. No file editing required.

### Environment Variables (Optional)

Create a `.env` file in the project root:

```env
APP_ENV=production
APP_URL=https://your-domain.com
```

> **Note:** API keys are managed through the application UI, not through environment variables.

---

## Deployment Guide

### Shared Hosting (cPanel)

1. Upload all files to your `public_html` directory
2. Run `composer install` (via SSH or upload vendor directory)
3. Point your domain to the `public/` directory
4. Ensure the `storage/` directory is writable (chmod 755)

### VPS / Dedicated Server

1. Clone or upload the project
2. Run `composer install`
3. Configure your web server to point to the `public/` directory
4. Ensure proper permissions on `storage/`

### XAMPP / Local Server

1. Place the project in your `htdocs` directory
2. Run `composer install`
3. Start Apache
4. Access via `http://localhost/data-light/public`

### PHP Built-in Server

```bash
cd data-light
composer install
php -S localhost:8000 -t public
```

---

## Dependencies

| Package | Version | Purpose |
|---------|---------|---------|
| phpoffice/phpspreadsheet | ^2.0 | Excel file reading (.xlsx, .xls) |
| vlucas/phpdotenv | ^5.6 | Environment variable loading |

---

## Browser Support

- Chrome 90+
- Firefox 88+
- Safari 14+
- Edge 90+

---

## License

MIT License

---

## Credits

**Developed by Suraj Dubey**

- Website: [https://mrsuraj.rf.gd](https://mrsuraj.rf.gd)
- GitHub: [https://github.com/5ur4jd-dev](https://github.com/5ur4jd-dev)

---

## Support

For issues, feature requests, or contributions, please visit the GitHub repository.

