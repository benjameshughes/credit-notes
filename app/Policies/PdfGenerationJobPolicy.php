<?php

namespace App\Policies;

use App\Models\PdfGenerationJob;
use App\Models\User;

class PdfGenerationJobPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, PdfGenerationJob $pdfGenerationJob): bool
    {
        return $user->id === $pdfGenerationJob->user_id;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, PdfGenerationJob $pdfGenerationJob): bool
    {
        return $user->id === $pdfGenerationJob->user_id;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, PdfGenerationJob $pdfGenerationJob): bool
    {
        return $user->id === $pdfGenerationJob->user_id &&
               in_array($pdfGenerationJob->status, ['completed', 'failed', 'completed_with_errors']);
    }

    /**
     * Determine whether the user can download the model.
     */
    public function download(User $user, PdfGenerationJob $pdfGenerationJob): bool
    {
        return $user->id === $pdfGenerationJob->user_id &&
               in_array($pdfGenerationJob->status, ['completed', 'completed_with_errors']) &&
               ($pdfGenerationJob->zip_path || $pdfGenerationJob->single_pdf_path);
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, PdfGenerationJob $pdfGenerationJob): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, PdfGenerationJob $pdfGenerationJob): bool
    {
        return false;
    }
}
