# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Core Application Purpose

This is a Laravel 12 application for processing CSV files and generating credit note PDFs. The application allows users to upload CSV files, which are processed in background jobs to generate individual PDF credit notes that are packaged into downloadable ZIP files.

## Development Commands

### Running the Application
```bash
# Start full development environment (server, queue, logs, vite)
composer dev

# Individual services
php artisan serve                    # Start Laravel server
php artisan queue:listen --tries=1   # Process background jobs
php artisan reverb:start             # Start WebSocket server
php artisan pail                     # View logs
npm run dev                          # Start Vite development server
```

### Testing
```bash
# Run all tests
composer test
# OR
php artisan test

# Run specific test suites
php artisan test --testsuite=Feature
php artisan test --testsuite=Unit

# Use Pest for individual tests
vendor/bin/pest tests/Feature/CsvToPdfWorkflowTest.php
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

### Maintenance Commands
```bash
# Clean up pending jobs older than 30 minutes (default)
php artisan jobs:cleanup-pending

# Clean up pending jobs older than specific hours
php artisan jobs:cleanup-pending --hours=2
```

### Frontend Build
```bash
npm run build    # Production build
npm run dev      # Development server
```

## Architecture & Key Components

### CSV to PDF Processing Pipeline
1. **Upload**: `CsvToPdfProcessor` Livewire component handles file uploads with validation
2. **Job Creation**: Creates individual `PdfGenerationJob` records per CSV row
3. **Queue Processing**: `ProcessCsvToPdf` jobs dispatched with 1-hour timeout
4. **PDF Generation**: Spatie Laravel PDF renders Blade templates using Chromium (supports modern CSS)
5. **Progress Broadcasting**: Real-time updates via `JobProgressUpdated` event
6. **File Management**: Smart download logic - single PDF or ZIP for multiple files
7. **Cleanup**: Automatic file deletion when jobs are deleted

### Core Technologies
- **Laravel 12** with **Livewire 3** for reactive UI components
- **Livewire Flux** for modern UI components
- **Laravel Reverb** for WebSocket server (first-party solution)
- **SQLite** database (file-based at `database/database.sqlite`)
- **Queue system** using database driver
- **Spatie Laravel PDF** for PDF generation (full modern CSS support via Chromium)
- **Tailwind CSS 4** with Vite for styling
- **Pest** for testing framework

### Key Classes & Architecture
- `app/Livewire/CsvToPdfProcessor.php` - Main UI component with user isolation
- `app/Jobs/ProcessCsvToPdf.php` - Background job with race condition prevention
- `app/Models/PdfGenerationJob.php` - Job tracking with user relationships
- `app/Events/JobProgressUpdated.php` - Broadcasting with `ShouldBroadcastNow`
- `app/Policies/PdfGenerationJobPolicy.php` - User authorization for all actions
- `app/Console/Commands/CleanupPendingJobs.php` - Scheduled maintenance
- `resources/views/pdf/credit-note-template.blade.php` - PDF template

### Database Schema
- Users table (standard Laravel auth)
- `pdf_generation_jobs` table tracks batch processing:
  - `user_id` for ownership and isolation
  - `batch_id`, `original_filename`, `total_rows`
  - `processed_rows`, `failed_rows`, `status`
  - `file_paths` (JSON), `zip_path`
  - Indexes on `user_id`, `batch_id`, `status`

### WebSocket/Broadcasting Architecture
- **Event**: `JobProgressUpdated` implements `ShouldBroadcastNow`
- **Channel Pattern**: `batch.{batchId}` for job-specific updates
- **Client Integration**: Laravel Echo with Pusher JS driver
- **Connection Management**: Automatic channel subscription/unsubscription
- **Configuration**: Uses Laravel Reverb (not Pusher)

### Queue Processing Details
- **Driver**: Database queue (configurable to Redis)
- **Timeout**: 1 hour per job (3600 seconds)
- **Retries**: Set via command line (`--tries=1` recommended)
- **Race Conditions**: Prevented using database locking in batch completion
- **Failed Jobs**: Tracked and status updated appropriately

### Security & User Isolation
- **Policy-based Authorization**: All actions go through `PdfGenerationJobPolicy`
- **User Scoping**: Jobs automatically scoped to authenticated user
- **File Access**: Download routes verify ownership before serving
- **CSRF Protection**: Enabled on all forms
- **Authentication**: Required for all application routes

### Storage Locations
- Temp CSV files: `storage/app/temp-csv/`
- Generated PDFs: `storage/app/pdfs/{batch_id}/`
- ZIP downloads: `storage/app/downloads/`
- Files auto-deleted when parent job is deleted

### Testing Setup
- Uses SQLite in-memory database (`:memory:`)
- Synchronous queue processing for predictable tests
- Feature tests cover full workflows
- Unit tests for isolated components
- Test data uses factory patterns

### Production Deployment Considerations
1. **Environment Variables**:
   ```env
   BROADCAST_CONNECTION=reverb    # Not pusher!
   QUEUE_CONNECTION=database      # Or redis
   REVERB_HOST=yourdomain.com     # Not localhost
   REVERB_PORT=443                # For WSS
   REVERB_SCHEME=https            # For production
   ```

2. **Required Daemons** (Forge/Supervisor):
   - `php artisan queue:work` - Queue processing
   - `php artisan reverb:start` - WebSocket server
   - `php artisan schedule:work` - For cleanup tasks

3. **WebSocket Configuration**:
   - Nginx proxy configuration required for WebSocket
   - SSL certificate must cover domain
   - Firewall must allow WebSocket port

4. **Known Limitations**:
   - Requires Node.js and Chromium for PDF generation
   - Large CSV files may need memory_limit adjustments
   - WebSocket connections need proper proxy setup
