<?php

namespace App\Http\Controllers;

use App\Models\Report;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class ReportController extends Controller
{
    /**
     * Display a listing of the reports.
     */
    public function index(Request $request)
    {
        try {
            $user = Auth::user();
            $role = $user->role;
            
            $query = Report::forRole($role);
            
            // Filter by type if provided
            if ($request->has('type') && $request->type != 'all') {
                $query->ofType($request->type);
            }
            
            // Filter by role (Admin only)
            if ($request->has('role') && $request->role != 'all' && $role === 'admin') {
                $query->where('role', $request->role);
            }
            
            // Search
            if ($request->has('search') && $request->search) {
                $search = $request->search;
                $query->where(function($q) use ($search) {
                    $q->where('title', 'LIKE', "%{$search}%")
                      ->orWhere('description', 'LIKE', "%{$search}%")
                      ->orWhere('file_name', 'LIKE', "%{$search}%");
                });
            }
            
            // Date range filter
            if ($request->has('start_date') && $request->has('end_date')) {
                $query->whereBetween('date_uploaded', [$request->start_date, $request->end_date]);
            }
            
            $reports = $query->orderBy('created_at', 'desc')->get();
            
            return response()->json($reports);
            
        } catch (\Exception $e) {
            Log::error('Report index error: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to fetch reports: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Store a newly uploaded report.
     */
    public function upload(Request $request)
    {
        try {
            Log::info('Upload request received', ['files' => $request->hasFile('file')]);
            
            $request->validate([
                'title' => 'required|string|max:255',
                'type' => 'required|string',
                'role' => 'nullable|string|in:admin,operations,accounting',
                'description' => 'nullable|string',
                'file' => 'required|file|max:10240',
            ]);

            $user = Auth::user();
            $role = $user->role;
            
            // Allow admin to choose role
            if ($role === 'admin' && $request->has('role')) {
                $role = $request->role;
            }
            
            $file = $request->file('file');
            $fileName = time() . '_' . $file->getClientOriginalName();
            $filePath = $file->storeAs('reports/' . $role, $fileName, 'public');
            
            // Check if storage path exists
            if (!Storage::disk('public')->exists('reports/' . $role)) {
                Storage::disk('public')->makeDirectory('reports/' . $role);
            }
            
            // Generate unique report ID
            $reportId = Report::generateReportId();
            
            $report = Report::create([
                'report_id' => $reportId,
                'title' => $request->title,
                'type' => $request->type,
                'role' => $role,
                'description' => $request->description,
                'file_name' => $file->getClientOriginalName(),
                'file_path' => $filePath,
                'file_size' => $file->getSize(),
                'date_uploaded' => now()->format('Y-m-d'),
                'uploaded_by' => $user->name,
                'status' => 'Completed',
                'user_id' => $user->id,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Report uploaded successfully!',
                'report' => $report
            ], 201);
            
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed: ' . $e->getMessage(),
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Upload error: ' . $e->getMessage());
            Log::error('Upload error trace: ' . $e->getTraceAsString());
            return response()->json([
                'success' => false,
                'message' => 'Upload failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Download a report file.
     */
    public function download($id)
    {
        try {
            $report = Report::where('report_id', $id)->firstOrFail();
            $user = Auth::user();
            
            // Admin can download any report
            if ($user->role !== 'admin' && $report->role !== $user->role) {
                return response()->json(['error' => 'You do not have access to this report.'], 403);
            }
            
            if (!Storage::disk('public')->exists($report->file_path)) {
                return response()->json(['error' => 'File not found.'], 404);
            }
            
            // Check if inline view is requested
            $inline = request()->has('inline');
            
            if ($inline) {
                // For inline viewing (PDF, images, etc.)
                $mimeType = Storage::disk('public')->mimeType($report->file_path);
                $fileContent = Storage::disk('public')->get($report->file_path);
                
                // For PDF files, ensure proper display
                if ($mimeType === 'application/pdf') {
                    return response($fileContent)
                        ->header('Content-Type', 'application/pdf')
                        ->header('Content-Disposition', 'inline; filename="' . $report->file_name . '"')
                        ->header('Cache-Control', 'public, max-age=3600');
                }
                
                // For other file types
                return response($fileContent)
                    ->header('Content-Type', $mimeType)
                    ->header('Content-Disposition', 'inline; filename="' . $report->file_name . '"')
                    ->header('Cache-Control', 'public, max-age=3600');
            }
            
            return Storage::disk('public')->download($report->file_path, $report->file_name);
            
        } catch (\Exception $e) {
            Log::error('Download error: ' . $e->getMessage());
            return response()->json(['error' => 'Download failed: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Delete a report.
     */
    public function destroy($id)
    {
        try {
            $report = Report::where('report_id', $id)->firstOrFail();
            $user = Auth::user();
            
            // Admin can delete any report, others can only delete their own
            if ($user->role !== 'admin' && $report->user_id !== $user->id) {
                return response()->json(['error' => 'You do not have permission to delete this report.'], 403);
            }
            
            // Delete file from storage
            if (Storage::disk('public')->exists($report->file_path)) {
                Storage::disk('public')->delete($report->file_path);
            }
            
            $report->delete();
            
            return response()->json([
                'success' => true,
                'message' => 'Report deleted successfully!'
            ]);
            
        } catch (\Exception $e) {
            Log::error('Delete error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Delete failed: ' . $e->getMessage()
            ], 500);
        }
    }
}