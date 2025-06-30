# Credit Notes Generator

A Laravel 12 application for processing CSV files and generating credit note PDFs with real-time progress tracking.

## Features

- 🔄 **CSV to PDF Processing**: Upload CSV files and automatically generate individual credit note PDFs
- ⚡ **Real-time Updates**: Live progress tracking using Laravel Reverb WebSockets
- 📦 **Smart Downloads**: Automatically creates ZIP files for multiple PDFs or serves single PDFs directly
- 🎨 **Modern UI**: Dark mode interface using Tailwind CSS and Flux UI components
- 🔐 **User Authentication**: Secure access with Laravel's built-in authentication
- 👤 **User Isolation**: Each user can only see and manage their own jobs and files
- 🗑️ **Job Management**: Delete completed jobs and their associated files
- 📊 **Organized Interface**: Separate sections for active/processing and completed jobs
- 🛡️ **Laravel Policies**: Proper authorization using Laravel's policy system
- 💾 **Background Processing**: Queue-based processing for handling large CSV files
- 🏢 **Professional Templates**: Company-branded PDF templates with VAT compliance

## Tech Stack

- **Laravel 12** - Backend framework
- **Livewire 3** - Reactive frontend components
- **Laravel Reverb** - Real-time WebSocket server (first-party)
- **Laravel Echo** - Frontend WebSocket client
- **Spatie Laravel PDF** - PDF generation using Chromium/Browsershot
- **Tailwind CSS 4** - Utility-first CSS framework
- **Flux UI** - Modern UI components for Livewire
- **SQLite** - File-based database
- **Pest** - Testing framework

## Installation

### Requirements

- PHP 8.2+
- Composer
- Node.js & NPM
- SQLite

### Setup

1. **Clone the repository**
   ```bash
   git clone https://github.com/benjameshughes/credit-notes.git
   cd credit-notes
   ```

2. **Install dependencies**
   ```bash
   composer install
   npm install
   ```

3. **Environment setup**
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```

4. **Database setup**
   ```bash
   touch database/database.sqlite
   php artisan migrate
   ```

5. **Build assets**
   ```bash
   npm run build
   ```

## Development

### Quick Start

Run the full development environment with one command:

```bash
composer dev
```

This starts:
- Laravel development server (`php artisan serve`)
- Queue worker (`php artisan queue:listen`)
- Laravel Reverb WebSocket server (`php artisan reverb:start`)
- Log viewer (`php artisan pail`)
- Vite development server (`npm run dev`)

### Individual Services

```bash
# Start Laravel server
php artisan serve

# Process background jobs
php artisan queue:listen

# Start WebSocket server for real-time updates
php artisan reverb:start

# View application logs
php artisan pail

# Start frontend development server
npm run dev
```

### Testing

```bash
# Run all tests
composer test

# Run specific test suites
php artisan test --testsuite=Feature
php artisan test --testsuite=Unit

# Run individual test files
vendor/bin/pest tests/Feature/CsvProcessingTest.php
```

### Code Quality

```bash
# Format code with Laravel Pint
vendor/bin/pint

# Clear Laravel caches
php artisan config:clear
php artisan cache:clear
php artisan view:clear
```

### Maintenance

```bash
# Clean up old pending jobs (default: older than 30 minutes)
php artisan jobs:cleanup-pending

# Clean up pending jobs older than specific hours
php artisan jobs:cleanup-pending --hours=2
```

## How It Works

### CSV Processing Pipeline

1. **Upload**: User uploads CSV file via Livewire component
2. **Job Creation**: System creates individual `PdfGenerationJob` records for each CSV row
3. **Queue Processing**: Background jobs process each row and generate PDFs
4. **Real-time Updates**: Progress broadcasted via Laravel Reverb WebSockets
5. **File Management**: 
   - Single PDF: Served directly for download
   - Multiple PDFs: Packaged into ZIP file
6. **Cleanup**: Temporary files cleaned up after packaging

### Architecture

- **`CsvToPdfProcessor`** - Main Livewire component handling uploads and UI with separated processing/completed job views
- **`ProcessCsvToPdf`** - Queue job for individual PDF generation
- **`PdfGenerationJob`** - Eloquent model tracking job progress with user relationships and file cleanup
- **`PdfGenerationJobPolicy`** - Laravel policy for user authorization and job management permissions
- **`JobProgressUpdated`** - Broadcast event for real-time updates using `ShouldBroadcastNow`
- **`CleanupPendingJobs`** - Scheduled command to remove stuck pending jobs
- **Credit Note Template** - Blade template for PDF generation (DomPDF compatible)

### File Structure

```
app/
├── Console/Commands/CleanupPendingJobs.php  # Automated cleanup command
├── Events/JobProgressUpdated.php            # Real-time progress broadcasting
├── Jobs/ProcessCsvToPdf.php                 # Background PDF generation
├── Livewire/CsvToPdfProcessor.php           # Main UI component with user isolation
├── Models/PdfGenerationJob.php              # Job tracking model with user relationships
└── Policies/PdfGenerationJobPolicy.php     # Authorization policies

resources/views/
├── livewire/csv-to-pdf-processor.blade.php  # Main interface with separate job sections
└── pdf/credit-note-template.blade.php       # PDF template (DomPDF compatible)

routes/
└── console.php                              # Scheduled tasks configuration

storage/
├── app/temp-csv/          # Temporary CSV files
├── app/pdfs/{batch_id}/   # Generated PDF files (auto-cleaned on job deletion)
└── app/downloads/         # ZIP downloads
```

## Configuration

### Environment Variables

```env
# Database
DB_CONNECTION=sqlite
DB_DATABASE=/absolute/path/to/database/database.sqlite

# Queue
QUEUE_CONNECTION=database

# Broadcasting (Laravel Reverb)
BROADCAST_CONNECTION=reverb
REVERB_APP_ID=your-app-id
REVERB_APP_KEY=your-app-key
REVERB_APP_SECRET=your-app-secret
REVERB_HOST=localhost
REVERB_PORT=8080
REVERB_SCHEME=http

# Vite (for real-time updates)
VITE_REVERB_APP_KEY="${REVERB_APP_KEY}"
VITE_REVERB_HOST="${REVERB_HOST}"
VITE_REVERB_PORT="${REVERB_PORT}"
VITE_REVERB_SCHEME="${REVERB_SCHEME}"
```

## Production Deployment

### Laravel Forge

See `deploy.sh` for the complete deployment script.

### Manual Deployment

1. **Server Requirements**
   - PHP 8.2+ with required extensions
   - Composer
   - Node.js & NPM
   - SQLite or MySQL
   - Process manager (Supervisor) for queues
   - Web server (Nginx/Apache)

2. **Environment Setup**
   ```bash
   composer install --no-dev --optimize-autoloader
   npm ci && npm run build
   php artisan config:cache
   php artisan route:cache
   php artisan view:cache
   php artisan migrate --force
   ```

3. **Queue & WebSocket Setup**
   - Configure Supervisor for `queue:work`
   - Set up process monitoring for `reverb:start`
   - Configure web server for WebSocket proxying

## Features in Detail

### User Management & Security

The application provides complete user isolation and management:

- **User-Specific Jobs**: Each user can only see and manage their own PDF generation jobs
- **Laravel Policies**: Authorization handled through `PdfGenerationJobPolicy` with methods for:
  - `view()` - Users can only view their own jobs
  - `delete()` - Users can only delete their own completed jobs
  - `download()` - Users can only download their own completed files
- **Secure Downloads**: All download routes check user ownership before serving files
- **Job Deletion**: Delete completed jobs along with all associated PDF and ZIP files
- **Automatic Cleanup**: When jobs are deleted, all related files are automatically removed from storage

### Interface Organization

The main interface is organized into two distinct sections:

- **Active Jobs Section**: Shows currently pending and processing jobs with:
  - Real-time progress updates
  - Animated progress bars
  - Live status indicators
  - Job count in section header
- **Completed Jobs Section**: Shows finished jobs with:
  - Download buttons for available files
  - Delete buttons for job cleanup
  - Final completion status
  - Error indicators if applicable

### Real-time Progress Tracking

The application uses Laravel Reverb for real-time updates:

- **Server-side**: Jobs broadcast progress events via `JobProgressUpdated`
- **Client-side**: Laravel Echo listens for WebSocket events and updates UI
- **Channels**: Each batch has its own channel (`batch.{batchId}`)
- **Live Job Movement**: Jobs automatically move from active to completed section when finished

### Smart Download Logic

- **Single PDF**: Downloads individual PDF file directly
- **Multiple PDFs**: Creates ZIP archive with all PDFs
- **Automatic Cleanup**: Removes individual PDFs after ZIP creation
- **User Authorization**: All downloads verified through Laravel policies

### Error Handling & Maintenance

- **Robust Exception Handling**: Jobs always update status, even on failure
- **Race Condition Prevention**: Database locking prevents concurrent batch completion
- **Progress Synchronization**: Real-time updates stay in sync with database state
- **Authorization Checks**: All user actions verified through policies before execution
- **Automatic Cleanup**: Scheduled task removes stuck pending jobs every 30 minutes
- **DomPDF Compatibility**: PDF templates use float-based layouts instead of modern CSS features

## Contributing

1. Fork the repository
2. Create a feature branch (`git checkout -b feature/amazing-feature`)
3. Commit your changes (`git commit -m 'Add amazing feature'`)
4. Push to the branch (`git push origin feature/amazing-feature`)
5. Open a Pull Request

## License

This project is open-sourced software licensed under the [MIT license](LICENSE).

## Support

For issues and questions, please use the [GitHub Issues](https://github.com/benjameshughes/credit-notes/issues) page.