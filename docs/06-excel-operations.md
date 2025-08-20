# Excel Import/Export Operations

## Overview

This comprehensive guide covers Excel import/export functionality using Laravel Excel (Maatwebsite/Laravel-Excel), providing secure, efficient, and user-friendly data processing capabilities. These standards ensure data integrity, performance optimization, and proper error handling for large datasets.

## Table of Contents

- [Installation & Setup](#installation--setup)
- [Export Operations](#export-operations)
- [Import Operations](#import-operations)
- [Data Validation](#data-validation)
- [Error Handling](#error-handling)
- [Performance Optimization](#performance-optimization)
- [Security Considerations](#security-considerations)
- [Queue Integration](#queue-integration)
- [Progress Tracking](#progress-tracking)
- [File Management](#file-management)
- [Advanced Features](#advanced-features)
- [Best Practices](#best-practices)

## Installation & Setup

### 1. Package Installation

```bash
# Install Laravel Excel
composer require maatwebsite/excel

# Publish configuration
php artisan vendor:publish --provider="Maatwebsite\Excel\ExcelServiceProvider" --tag=config

# Create storage directories
php artisan storage:link
```

### 2. Configuration

```php
<?php

// config/excel.php
return [
    'exports' => [
        'chunk_size' => 1000,
        'pre_calculate_formulas' => false,
        'strict_null_comparison' => false,
        'csv' => [
            'delimiter' => ',',
            'enclosure' => '"',
            'escape_character' => '\\',
            'contiguous' => false,
            'input_encoding' => 'UTF-8',
        ],
        'properties' => [
            'creator' => 'MP Software',
            'lastModifiedBy' => 'MP Software',
            'title' => 'Export',
            'description' => 'Generated Export',
            'subject' => 'Export',
            'keywords' => 'excel,export',
            'category' => 'Reports',
            'manager' => 'MP Software',
            'company' => 'MP Software',
        ],
    ],

    'imports' => [
        'read_only' => true,
        'ignore_empty' => false,
        'heading_row' => [
            'formatter' => 'slug',
        ],
        'csv' => [
            'auto_detect' => true,
            'delimiter' => ',',
            'enclosure' => '"',
            'escape_character' => '\\',
            'contiguous' => false,
            'input_encoding' => 'UTF-8',
        ],
        'properties' => [
            'read_data_only' => true,
            'read_charts' => false,
            'read_empty_cells' => false,
        ],
    ],

    'extension_detector' => [
        'xlsx' => 'Xlsx',
        'xlsm' => 'Xlsx',
        'xltx' => 'Xlsx',
        'xltm' => 'Xlsx',
        'xls' => 'Xls',
        'xlt' => 'Xls',
        'ods' => 'Ods',
        'ots' => 'Ods',
        'slk' => 'Slk',
        'xml' => 'Xml',
        'gnumeric' => 'Gnumeric',
        'htm' => 'Html',
        'html' => 'Html',
        'csv' => 'Csv',
        'tsv' => 'Csv',
    ],

    'value_binder' => [
        'default' => Maatwebsite\Excel\DefaultValueBinder::class,
    ],

    'cache' => [
        'driver' => 'memory',
        'batch' => [
            'memory_limit' => 60000,
        ],
        'illuminate' => [
            'store' => null,
        ],
    ],

    'transactions' => [
        'handler' => 'db',
        'db' => [
            'connection' => null,
        ],
    ],

    'temporary_files' => [
        'local_path' => storage_path('app/temp'),
        'remote_disk' => null,
        'remote_prefix' => null,
        'force_resync_remote' => null,
    ],
];
```

### 3. Environment Configuration

```env
# Excel Configuration
EXCEL_CACHE_DRIVER=memory
EXCEL_CHUNK_SIZE=1000
EXCEL_MEMORY_LIMIT=512M
EXCEL_MAX_EXECUTION_TIME=300

# File Upload Limits
EXCEL_MAX_FILE_SIZE=50M
EXCEL_ALLOWED_EXTENSIONS=xlsx,xls,csv

# Queue Configuration for Large Files
EXCEL_QUEUE_CONNECTION=redis
EXCEL_QUEUE_NAME=excel-processing
```

## Export Operations

### 1. Basic Export Classes

```php
<?php

namespace App\Exports;

use App\Models\Project;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

class ProjectsExport implements 
    FromCollection, 
    WithHeadings, 
    WithMapping, 
    WithColumnFormatting,
    WithStyles,
    ShouldAutoSize,
    WithEvents
{
    protected $filters;
    protected $user;

    public function __construct(array $filters = [], $user = null)
    {
        $this->filters = $filters;
        $this->user = $user;
    }

    public function collection()
    {
        $query = Project::with(['owner:id,name', 'department:id,name'])
            ->select([
                'id', 'title', 'code', 'description', 'status', 'priority',
                'owner_id', 'department_id', 'budget', 'actual_cost',
                'planned_start_date', 'planned_end_date', 'actual_start_date',
                'actual_end_date', 'progress_percentage', 'created_at'
            ]);

        // Apply filters
        if (!empty($this->filters['status'])) {
            $query->where('status', $this->filters['status']);
        }

        if (!empty($this->filters['department_id'])) {
            $query->where('department_id', $this->filters['department_id']);
        }

        if (!empty($this->filters['date_from'])) {
            $query->where('created_at', '>=', $this->filters['date_from']);
        }

        if (!empty($this->filters['date_to'])) {
            $query->where('created_at', '<=', $this->filters['date_to']);
        }

        // Apply user permissions
        if ($this->user && !$this->user->hasRole('Admin')) {
            $query->where(function ($q) {
                $q->where('owner_id', $this->user->id)
                  ->orWhereHas('members', function ($memberQuery) {
                      $memberQuery->where('user_id', $this->user->id);
                  });
            });
        }

        return $query->get();
    }

    public function headings(): array
    {
        return [
            'ID',
            'Title',
            'Code',
            'Description',
            'Status',
            'Priority',
            'Owner',
            'Department',
            'Budget',
            'Actual Cost',
            'Planned Start',
            'Planned End',
            'Actual Start',
            'Actual End',
            'Progress %',
            'Created At',
        ];
    }

    public function map($project): array
    {
        return [
            $project->id,
            $project->title,
            $project->code,
            $project->description,
            ucfirst($project->status),
            ucfirst($project->priority),
            $project->owner->name ?? 'N/A',
            $project->department->name ?? 'N/A',
            $project->budget,
            $project->actual_cost,
            $project->planned_start_date?->format('Y-m-d'),
            $project->planned_end_date?->format('Y-m-d'),
            $project->actual_start_date?->format('Y-m-d'),
            $project->actual_end_date?->format('Y-m-d'),
            $project->progress_percentage,
            $project->created_at->format('Y-m-d H:i:s'),
        ];
    }

    public function columnFormats(): array
    {
        return [
            'I' => NumberFormat::FORMAT_CURRENCY_USD_SIMPLE, // Budget
            'J' => NumberFormat::FORMAT_CURRENCY_USD_SIMPLE, // Actual Cost
            'K' => NumberFormat::FORMAT_DATE_YYYYMMDD,        // Planned Start
            'L' => NumberFormat::FORMAT_DATE_YYYYMMDD,        // Planned End
            'M' => NumberFormat::FORMAT_DATE_YYYYMMDD,        // Actual Start
            'N' => NumberFormat::FORMAT_DATE_YYYYMMDD,        // Actual End
            'O' => NumberFormat::FORMAT_PERCENTAGE,           // Progress
            'P' => NumberFormat::FORMAT_DATE_DATETIME,        // Created At
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            // Header row styling
            1 => [
                'font' => [
                    'bold' => true,
                    'color' => ['rgb' => 'FFFFFF'],
                ],
                'fill' => [
                    'fillType' => 'solid',
                    'color' => ['rgb' => '366092'],
                ],
            ],
            
            // Data rows styling
            'A:P' => [
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => 'thin',
                        'color' => ['rgb' => 'CCCCCC'],
                    ],
                ],
            ],
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function(AfterSheet $event) {
                // Freeze header row
                $event->sheet->freezePane('A2');
                
                // Add filters
                $event->sheet->setAutoFilter('A1:P1');
                
                // Add summary at the bottom
                $lastRow = $event->sheet->getHighestRow() + 2;
                
                $event->sheet->setCellValue("A{$lastRow}", 'Summary:');
                $event->sheet->setCellValue("B{$lastRow}", '=COUNTA(A2:A' . ($lastRow - 2) . ')');
                $event->sheet->setCellValue("I{$lastRow}", '=SUM(I2:I' . ($lastRow - 2) . ')');
                $event->sheet->setCellValue("J{$lastRow}", '=SUM(J2:J' . ($lastRow - 2) . ')');
                
                // Style summary row
                $event->sheet->getStyle("A{$lastRow}:P{$lastRow}")->applyFromArray([
                    'font' => ['bold' => true],
                    'fill' => [
                        'fillType' => 'solid',
                        'color' => ['rgb' => 'F0F0F0'],
                    ],
                ]);
            },
        ];
    }
}
```

### 2. Advanced Export with Multiple Sheets

```php
<?php

namespace App\Exports;

use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class ComprehensiveProjectReport implements WithMultipleSheets
{
    use Exportable;

    protected $filters;
    protected $user;

    public function __construct(array $filters = [], $user = null)
    {
        $this->filters = $filters;
        $this->user = $user;
    }

    public function sheets(): array
    {
        return [
            'Projects' => new ProjectsExport($this->filters, $this->user),
            'Tasks' => new TasksExport($this->filters, $this->user),
            'Summary' => new ProjectSummaryExport($this->filters, $this->user),
            'Charts' => new ProjectChartsExport($this->filters, $this->user),
        ];
    }
}

class TasksExport implements FromCollection, WithHeadings, WithMapping
{
    protected $filters;
    protected $user;

    public function __construct(array $filters = [], $user = null)
    {
        $this->filters = $filters;
        $this->user = $user;
    }

    public function collection()
    {
        $query = Task::with(['project:id,title', 'assignee:id,name', 'creator:id,name'])
            ->select([
                'id', 'title', 'description', 'status', 'priority', 'type',
                'project_id', 'assigned_to', 'created_by', 'estimated_hours',
                'actual_hours', 'progress_percentage', 'due_date', 'created_at'
            ]);

        // Apply project filters if specified
        if (!empty($this->filters['project_id'])) {
            $query->where('project_id', $this->filters['project_id']);
        }

        if (!empty($this->filters['status'])) {
            $query->where('status', $this->filters['status']);
        }

        // Apply user permissions
        if ($this->user && !$this->user->hasRole('Admin')) {
            $query->whereHas('project', function ($projectQuery) {
                $projectQuery->where('owner_id', $this->user->id)
                           ->orWhereHas('members', function ($memberQuery) {
                               $memberQuery->where('user_id', $this->user->id);
                           });
            });
        }

        return $query->get();
    }

    public function headings(): array
    {
        return [
            'Task ID', 'Title', 'Description', 'Status', 'Priority', 'Type',
            'Project', 'Assigned To', 'Created By', 'Estimated Hours',
            'Actual Hours', 'Progress %', 'Due Date', 'Created At'
        ];
    }

    public function map($task): array
    {
        return [
            $task->id,
            $task->title,
            $task->description,
            ucfirst($task->status),
            ucfirst($task->priority),
            ucfirst($task->type),
            $task->project->title ?? 'N/A',
            $task->assignee->name ?? 'Unassigned',
            $task->creator->name ?? 'N/A',
            $task->estimated_hours,
            $task->actual_hours,
            $task->progress_percentage,
            $task->due_date?->format('Y-m-d'),
            $task->created_at->format('Y-m-d H:i:s'),
        ];
    }
}
```

### 3. Export Service

```php
<?php

namespace App\Services;

use App\Exports\ProjectsExport;
use App\Exports\ComprehensiveProjectReport;
use App\Models\ExportLog;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ExcelExportService
{
    public function exportProjects(array $filters = [], $user = null, string $format = 'xlsx'): array
    {
        try {
            $filename = $this->generateFilename('projects', $format);
            $export = new ProjectsExport($filters, $user);
            
            // Store in temporary directory
            $path = "exports/temp/{$filename}";
            Excel::store($export, $path, 'local', \Maatwebsite\Excel\Excel::XLSX);
            
            // Log the export
            $this->logExport('projects', $filename, $user, $filters);
            
            return [
                'success' => true,
                'filename' => $filename,
                'path' => $path,
                'url' => Storage::url($path),
                'size' => Storage::size($path),
            ];
            
        } catch (\Exception $e) {
            \Log::error('Excel export failed', [
                'type' => 'projects',
                'user_id' => $user?->id,
                'filters' => $filters,
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'error' => 'Export failed: ' . $e->getMessage()
            ];
        }
    }

    public function exportComprehensiveReport(array $filters = [], $user = null): array
    {
        try {
            $filename = $this->generateFilename('comprehensive_report', 'xlsx');
            $export = new ComprehensiveProjectReport($filters, $user);
            
            $path = "exports/temp/{$filename}";
            Excel::store($export, $path, 'local', \Maatwebsite\Excel\Excel::XLSX);
            
            $this->logExport('comprehensive_report', $filename, $user, $filters);
            
            return [
                'success' => true,
                'filename' => $filename,
                'path' => $path,
                'url' => Storage::url($path),
                'size' => Storage::size($path),
            ];
            
        } catch (\Exception $e) {
            \Log::error('Comprehensive export failed', [
                'user_id' => $user?->id,
                'filters' => $filters,
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'error' => 'Export failed: ' . $e->getMessage()
            ];
        }
    }

    public function downloadExport(string $filename, $user = null): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $path = "exports/temp/{$filename}";
        
        if (!Storage::exists($path)) {
            abort(404, 'Export file not found');
        }

        // Verify user has permission to download this file
        $exportLog = ExportLog::where('filename', $filename)
                             ->where('user_id', $user?->id)
                             ->first();
        
        if (!$exportLog && $user && !$user->hasRole('Admin')) {
            abort(403, 'Unauthorized to download this file');
        }

        return Storage::download($path, $filename);
    }

    protected function generateFilename(string $type, string $format): string
    {
        $timestamp = now()->format('Y-m-d_H-i-s');
        $hash = Str::random(8);
        
        return "{$type}_{$timestamp}_{$hash}.{$format}";
    }

    protected function logExport(string $type, string $filename, $user, array $filters): void
    {
        ExportLog::create([
            'type' => $type,
            'filename' => $filename,
            'user_id' => $user?->id,
            'filters' => $filters,
            'file_size' => Storage::size("exports/temp/{$filename}"),
            'exported_at' => now(),
        ]);
    }

    public function cleanupOldExports(int $daysOld = 7): int
    {
        $cutoffDate = now()->subDays($daysOld);
        
        $oldExports = ExportLog::where('exported_at', '<', $cutoffDate)->get();
        $deletedCount = 0;
        
        foreach ($oldExports as $export) {
            $path = "exports/temp/{$export->filename}";
            
            if (Storage::exists($path)) {
                Storage::delete($path);
                $deletedCount++;
            }
            
            $export->delete();
        }
        
        return $deletedCount;
    }
}
```

## Import Operations

### 1. Basic Import Classes

```php
<?php

namespace App\Imports;

use App\Models\Project;
use App\Models\User;
use App\Models\Department;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use Maatwebsite\Excel\Concerns\WithBatchInserts;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\SkipsErrors;
use Maatwebsite\Excel\Concerns\SkipsFailures;
use Maatwebsite\Excel\Concerns\Importable;
use Maatwebsite\Excel\Validators\Failure;

class ProjectsImport implements 
    ToCollection, 
    WithHeadingRow, 
    WithValidation, 
    WithBatchInserts,
    WithChunkReading,
    SkipsErrors,
    SkipsFailures
{
    use Importable, SkipsErrors, SkipsFailures;

    protected $user;
    protected $importedCount = 0;
    protected $skippedCount = 0;
    protected $errors = [];

    public function __construct($user = null)
    {
        $this->user = $user;
    }

    public function collection(Collection $rows)
    {
        foreach ($rows as $row) {
            try {
                $this->processRow($row);
                $this->importedCount++;
            } catch (\Exception $e) {
                $this->skippedCount++;
                $this->errors[] = [
                    'row' => $this->importedCount + $this->skippedCount + 1,
                    'error' => $e->getMessage(),
                    'data' => $row->toArray()
                ];
            }
        }
    }

    protected function processRow(Collection $row)
    {
        // Find or create owner
        $owner = User::where('email', $row['owner_email'])->first();
        if (!$owner) {
            throw new \Exception("Owner with email {$row['owner_email']} not found");
        }

        // Find department if specified
        $department = null;
        if (!empty($row['department'])) {
            $department = Department::where('name', $row['department'])->first();
            if (!$department) {
                throw new \Exception("Department {$row['department']} not found");
            }
        }

        // Validate dates
        $startDate = $this->parseDate($row['planned_start_date']);
        $endDate = $this->parseDate($row['planned_end_date']);

        if ($endDate && $startDate && $endDate < $startDate) {
            throw new \Exception("End date cannot be before start date");
        }

        // Create or update project
        $projectData = [
            'title' => $row['title'],
            'description' => $row['description'] ?? '',
            'code' => $row['code'] ?? $this->generateProjectCode(),
            'owner_id' => $owner->id,
            'department_id' => $department?->id,
            'status' => $this->mapStatus($row['status'] ?? 'draft'),
            'priority' => $this->mapPriority($row['priority'] ?? 'medium'),
            'planned_start_date' => $startDate,
            'planned_end_date' => $endDate,
            'budget' => $this->parseDecimal($row['budget']),
        ];

        // Check if project already exists
        $existingProject = Project::where('code', $projectData['code'])->first();
        
        if ($existingProject) {
            $existingProject->update($projectData);
        } else {
            Project::create($projectData);
        }
    }

    public function rules(): array
    {
        return [
            'title' => 'required|string|max:255',
            'owner_email' => 'required|email|exists:users,email',
            'status' => 'nullable|in:draft,planning,active,on_hold,completed,cancelled',
            'priority' => 'nullable|in:low,medium,high,urgent',
            'budget' => 'nullable|numeric|min:0',
            'planned_start_date' => 'nullable|date',
            'planned_end_date' => 'nullable|date|after_or_equal:planned_start_date',
        ];
    }

    public function batchSize(): int
    {
        return 500;
    }

    public function chunkSize(): int
    {
        return 1000;
    }

    protected function parseDate($dateString)
    {
        if (empty($dateString)) {
            return null;
        }

        try {
            return \Carbon\Carbon::parse($dateString)->format('Y-m-d');
        } catch (\Exception $e) {
            throw new \Exception("Invalid date format: {$dateString}");
        }
    }

    protected function parseDecimal($value)
    {
        if (empty($value)) {
            return null;
        }

        // Remove currency symbols and commas
        $cleaned = preg_replace('/[^\d.]/', '', $value);
        return is_numeric($cleaned) ? (float) $cleaned : null;
    }

    protected function mapStatus($status): string
    {
        $statusMap = [
            'draft' => 'draft',
            'planning' => 'planning',
            'active' => 'active',
            'in progress' => 'active',
            'on hold' => 'on_hold',
            'completed' => 'completed',
            'done' => 'completed',
            'cancelled' => 'cancelled',
            'canceled' => 'cancelled',
        ];

        return $statusMap[strtolower($status)] ?? 'draft';
    }

    protected function mapPriority($priority): string
    {
        $priorityMap = [
            'low' => 'low',
            'medium' => 'medium',
            'normal' => 'medium',
            'high' => 'high',
            'urgent' => 'urgent',
            'critical' => 'urgent',
        ];

        return $priorityMap[strtolower($priority)] ?? 'medium';
    }

    protected function generateProjectCode(): string
    {
        do {
            $code = 'PRJ-' . strtoupper(Str::random(6));
        } while (Project::where('code', $code)->exists());

        return $code;
    }

    public function getImportSummary(): array
    {
        return [
            'imported' => $this->importedCount,
            'skipped' => $this->skippedCount,
            'errors' => $this->errors,
            'failures' => $this->failures(),
        ];
    }
}
```

### 2. Import Service

```php
<?php

namespace App\Services;

use App\Imports\ProjectsImport;
use App\Models\ImportLog;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ExcelImportService
{
    public function importProjects(UploadedFile $file, $user = null): array
    {
        try {
            // Validate file
            $this->validateFile($file);
            
            // Store file temporarily
            $filename = $this->storeTemporaryFile($file);
            
            // Create import instance
            $import = new ProjectsImport($user);
            
            // Process import
            Excel::import($import, $filename, 'local');
            
            // Get import summary
            $summary = $import->getImportSummary();
            
            // Log the import
            $this->logImport('projects', $filename, $user, $summary);
            
            // Clean up temporary file
            Storage::delete($filename);
            
            return [
                'success' => true,
                'summary' => $summary,
                'message' => "Import completed. {$summary['imported']} records imported, {$summary['skipped']} skipped."
            ];
            
        } catch (\Exception $e) {
            \Log::error('Excel import failed', [
                'type' => 'projects',
                'user_id' => $user?->id,
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'error' => 'Import failed: ' . $e->getMessage()
            ];
        }
    }

    public function validateImportFile(UploadedFile $file): array
    {
        $errors = [];
        
        // Check file size (50MB limit)
        if ($file->getSize() > 50 * 1024 * 1024) {
            $errors[] = 'File size exceeds 50MB limit';
        }
        
        // Check file extension
        $allowedExtensions = ['xlsx', 'xls', 'csv'];
        $extension = strtolower($file->getClientOriginalExtension());
        
        if (!in_array($extension, $allowedExtensions)) {
            $errors[] = 'File must be Excel (.xlsx, .xls) or CSV format';
        }
        
        // Check MIME type
        $allowedMimeTypes = [
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/vnd.ms-excel',
            'text/csv',
            'text/plain'
        ];
        
        if (!in_array($file->getMimeType(), $allowedMimeTypes)) {
            $errors[] = 'Invalid file type';
        }
        
        return $errors;
    }

    public function previewImport(UploadedFile $file, int $rows = 5): array
    {
        try {
            $this->validateFile($file);
            
            $filename = $this->storeTemporaryFile($file);
            
            // Read first few rows for preview
            $data = Excel::toArray(new \stdClass(), $filename, 'local');
            
            Storage::delete($filename);
            
            $preview = [];
            $headers = $data[0][0] ?? [];
            
            for ($i = 1; $i <= min($rows, count($data[0]) - 1); $i++) {
                if (isset($data[0][$i])) {
                    $preview[] = array_combine($headers, $data[0][$i]);
                }
            }
            
            return [
                'success' => true,
                'headers' => $headers,
                'preview' => $preview,
                'total_rows' => count($data[0]) - 1, // Exclude header row
            ];
            
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    public function getImportTemplate(string $type = 'projects'): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $templates = [
            'projects' => [
                'filename' => 'project_import_template.xlsx',
                'headers' => [
                    'title', 'description', 'code', 'owner_email', 'department',
                    'status', 'priority', 'planned_start_date', 'planned_end_date',
                    'budget'
                ],
                'sample_data' => [
                    [
                        'Website Redesign', 'Complete redesign of company website',
                        'PRJ-001', 'john@company.com', 'IT',
                        'active', 'high', '2024-01-15', '2024-06-30',
                        '50000.00'
                    ],
                    [
                        'Mobile App Development', 'Develop iOS and Android mobile application',
                        'PRJ-002', 'jane@company.com', 'Development',
                        'planning', 'medium', '2024-02-01', '2024-12-31',
                        '150000.00'
                    ]
                ]
            ]
        ];

        if (!isset($templates[$type])) {
            abort(404, 'Template not found');
        }

        $template = $templates[$type];
        
        return Excel::download(new class($template) implements \Maatwebsite\Excel\Concerns\FromArray, \Maatwebsite\Excel\Concerns\WithHeadings {
            protected $template;
            
            public function __construct($template)
            {
                $this->template = $template;
            }
            
            public function array(): array
            {
                return $this->template['sample_data'];
            }
            
            public function headings(): array
            {
                return $this->template['headers'];
            }
        }, $template['filename']);
    }

    protected function validateFile(UploadedFile $file): void
    {
        $errors = $this->validateImportFile($file);
        
        if (!empty($errors)) {
            throw new \InvalidArgumentException(implode(', ', $errors));
        }
    }

    protected function storeTemporaryFile(UploadedFile $file): string
    {
        $filename = 'imports/temp/' . Str::random(20) . '.' . $file->getClientOriginalExtension();
        $file->storeAs('', $filename, 'local');
        
        return $filename;
    }

    protected function logImport(string $type, string $filename, $user, array $summary): void
    {
        ImportLog::create([
            'type' => $type,
            'filename' => basename($filename),
            'user_id' => $user?->id,
            'imported_count' => $summary['imported'],
            'skipped_count' => $summary['skipped'],
            'error_count' => count($summary['errors']) + count($summary['failures']),
            'summary' => $summary,
            'imported_at' => now(),
        ]);
    }

    public function getImportHistory($user = null, int $limit = 20): \Illuminate\Pagination\LengthAwarePaginator
    {
        $query = ImportLog::with('user:id,name,email')->latest();
        
        if ($user && !$user->hasRole('Admin')) {
            $query->where('user_id', $user->id);
        }
        
        return $query->paginate($limit);
    }
}
```

## Data Validation

### 1. Custom Validation Rules

```php
<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class ExcelDateFormat implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (empty($value)) {
            return; // Allow empty values
        }

        // Try to parse various date formats
        $formats = [
            'Y-m-d',
            'm/d/Y',
            'd/m/Y',
            'Y-m-d H:i:s',
            'm/d/Y H:i:s',
            'd-m-Y',
            'd.m.Y',
        ];

        foreach ($formats as $format) {
            $date = \DateTime::createFromFormat($format, $value);
            if ($date && $date->format($format) === $value) {
                return; // Valid date found
            }
        }

        $fail('The :attribute field must be a valid date format.');
    }
}

class ProjectCodeUnique implements ValidationRule
{
    protected $exceptId;

    public function __construct($exceptId = null)
    {
        $this->exceptId = $exceptId;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $query = \App\Models\Project::where('code', $value);
        
        if ($this->exceptId) {
            $query->where('id', '!=', $this->exceptId);
        }
        
        if ($query->exists()) {
            $fail('The project code :input is already taken.');
        }
    }
}

class BudgetFormat implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (empty($value)) {
            return; // Allow empty values
        }

        // Remove currency symbols and commas
        $cleaned = preg_replace('/[^\d.]/', '', $value);
        
        if (!is_numeric($cleaned)) {
            $fail('The :attribute field must be a valid budget amount.');
            return;
        }

        $budget = (float) $cleaned;
        
        if ($budget < 0) {
            $fail('The :attribute field must be a positive amount.');
        }

        if ($budget > 10000000) { // 10 million limit
            $fail('The :attribute field cannot exceed $10,000,000.');
        }
    }
}
```

### 2. Row-Level Validation

```php
<?php

namespace App\Imports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use Maatwebsite\Excel\Concerns\SkipsOnError;
use Maatwebsite\Excel\Concerns\SkipsOnFailure;
use Maatwebsite\Excel\Validators\Failure;

class ValidatedProjectsImport implements ToCollection, WithHeadingRow, WithValidation, SkipsOnError, SkipsOnFailure
{
    protected $validationErrors = [];
    protected $processedRows = [];

    public function collection(Collection $rows)
    {
        foreach ($rows as $index => $row) {
            $rowNumber = $index + 2; // +2 for header row and 0-based index
            
            // Custom validation logic
            $errors = $this->validateRow($row, $rowNumber);
            
            if (!empty($errors)) {
                $this->validationErrors[] = [
                    'row' => $rowNumber,
                    'errors' => $errors,
                    'data' => $row->toArray()
                ];
                continue;
            }
            
            // Process valid row
            $this->processedRows[] = $this->transformRow($row);
        }
    }

    protected function validateRow(Collection $row, int $rowNumber): array
    {
        $errors = [];
        
        // Check required fields
        if (empty($row['title'])) {
            $errors[] = 'Title is required';
        }
        
        if (empty($row['owner_email'])) {
            $errors[] = 'Owner email is required';
        } elseif (!filter_var($row['owner_email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Owner email must be a valid email address';
        }
        
        // Check owner exists
        if (!empty($row['owner_email'])) {
            $owner = \App\Models\User::where('email', $row['owner_email'])->first();
            if (!$owner) {
                $errors[] = "Owner with email '{$row['owner_email']}' not found";
            }
        }
        
        // Check department exists
        if (!empty($row['department'])) {
            $department = \App\Models\Department::where('name', $row['department'])->first();
            if (!$department) {
                $errors[] = "Department '{$row['department']}' not found";
            }
        }
        
        // Validate dates
        if (!empty($row['planned_start_date'])) {
            if (!$this->isValidDate($row['planned_start_date'])) {
                $errors[] = 'Planned start date is not a valid date';
            }
        }
        
        if (!empty($row['planned_end_date'])) {
            if (!$this->isValidDate($row['planned_end_date'])) {
                $errors[] = 'Planned end date is not a valid date';
            } elseif (!empty($row['planned_start_date']) && $this->isValidDate($row['planned_start_date'])) {
                $startDate = \Carbon\Carbon::parse($row['planned_start_date']);
                $endDate = \Carbon\Carbon::parse($row['planned_end_date']);
                
                if ($endDate->lt($startDate)) {
                    $errors[] = 'Planned end date cannot be before start date';
                }
            }
        }
        
        // Validate budget
        if (!empty($row['budget'])) {
            $budget = $this->parseBudget($row['budget']);
            if ($budget === null) {
                $errors[] = 'Budget must be a valid number';
            } elseif ($budget < 0) {
                $errors[] = 'Budget cannot be negative';
            }
        }
        
        // Check project code uniqueness
        if (!empty($row['code'])) {
            $existing = \App\Models\Project::where('code', $row['code'])->first();
            if ($existing) {
                $errors[] = "Project code '{$row['code']}' already exists";
            }
        }
        
        return $errors;
    }

    protected function transformRow(Collection $row): array
    {
        return [
            'title' => trim($row['title']),
            'description' => trim($row['description'] ?? ''),
            'code' => $row['code'] ?? $this->generateProjectCode(),
            'owner_email' => strtolower(trim($row['owner_email'])),
            'department' => trim($row['department'] ?? ''),
            'status' => $this->mapStatus($row['status'] ?? 'draft'),
            'priority' => $this->mapPriority($row['priority'] ?? 'medium'),
            'planned_start_date' => $this->parseDate($row['planned_start_date']),
            'planned_end_date' => $this->parseDate($row['planned_end_date']),
            'budget' => $this->parseBudget($row['budget']),
        ];
    }

    protected function isValidDate($dateString): bool
    {
        if (empty($dateString)) {
            return false;
        }

        try {
            \Carbon\Carbon::parse($dateString);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    protected function parseDate($dateString)
    {
        if (empty($dateString)) {
            return null;
        }

        try {
            return \Carbon\Carbon::parse($dateString)->format('Y-m-d');
        } catch (\Exception $e) {
            return null;
        }
    }

    protected function parseBudget($budgetString)
    {
        if (empty($budgetString)) {
            return null;
        }

        // Remove currency symbols and commas
        $cleaned = preg_replace('/[^\d.]/', '', $budgetString);
        
        return is_numeric($cleaned) ? (float) $cleaned : null;
    }

    protected function mapStatus($status): string
    {
        $statusMap = [
            'draft' => 'draft',
            'planning' => 'planning',
            'active' => 'active',
            'on hold' => 'on_hold',
            'completed' => 'completed',
            'cancelled' => 'cancelled',
        ];

        return $statusMap[strtolower(trim($status))] ?? 'draft';
    }

    protected function mapPriority($priority): string
    {
        $priorityMap = [
            'low' => 'low',
            'medium' => 'medium',
            'high' => 'high',
            'urgent' => 'urgent',
        ];

        return $priorityMap[strtolower(trim($priority))] ?? 'medium';
    }

    protected function generateProjectCode(): string
    {
        do {
            $code = 'PRJ-' . strtoupper(\Illuminate\Support\Str::random(6));
        } while (\App\Models\Project::where('code', $code)->exists());

        return $code;
    }

    public function rules(): array
    {
        return [
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'owner_email' => 'required|email|exists:users,email',
            'department' => 'nullable|string|exists:departments,name',
            'status' => 'nullable|in:draft,planning,active,on_hold,completed,cancelled',
            'priority' => 'nullable|in:low,medium,high,urgent',
            'budget' => 'nullable|numeric|min:0',
            'planned_start_date' => 'nullable|date',
            'planned_end_date' => 'nullable|date|after_or_equal:planned_start_date',
        ];
    }

    public function onError(\Throwable $error)
    {
        \Log::error('Import row error', [
            'error' => $error->getMessage(),
            'trace' => $error->getTraceAsString()
        ]);
    }

    public function onFailure(Failure ...$failures)
    {
        foreach ($failures as $failure) {
            $this->validationErrors[] = [
                'row' => $failure->row(),
                'attribute' => $failure->attribute(),
                'errors' => $failure->errors(),
                'values' => $failure->values()
            ];
        }
    }

    public function getValidationErrors(): array
    {
        return $this->validationErrors;
    }

    public function getProcessedRows(): array
    {
        return $this->processedRows;
    }
}
```

---

**Next**: Continue with error handling, performance optimization, and security considerations sections.