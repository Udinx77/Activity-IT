<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Act\DashboardController;
use App\Http\Controllers\Act\ActivityController;
use App\Http\Controllers\Act\PendingController;
use App\Http\Controllers\Act\ClosedController;
use App\Http\Controllers\Act\MasterController;
use App\Http\Controllers\Act\ImportController;
use App\Http\Controllers\Act\TeamController;
use App\Http\Controllers\Act\PlanController;
use App\Http\Controllers\Act\ExportController;

// Section: Activity Plan & Action  (dimuat dari routes/web.php di dalam group middleware 'auth')
Route::prefix('activity')->name('act.')->group(function () {

    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Generate Tim (manual, dari roster) + Plan ceklis + Follow-up troubleshoot
    Route::get('teams/generate', [TeamController::class, 'generate'])->name('team.generate');
    Route::post('teams', [TeamController::class, 'store'])->name('team.store');
    Route::get('teams/{team}/edit', [TeamController::class, 'edit'])->name('team.edit');
    Route::put('teams/{team}', [TeamController::class, 'update'])->name('team.update');
    Route::delete('teams/{team}', [TeamController::class, 'destroy'])->name('team.destroy');

    Route::get('teams/{team}/plans/create', [PlanController::class, 'create'])->name('plan.create');
    Route::post('teams/{team}/plans', [PlanController::class, 'store'])->name('plan.store');
    Route::get('teams/{team}/carry', [PlanController::class, 'carryForm'])->name('plan.carry');
    Route::post('teams/{team}/carry', [PlanController::class, 'carryStore'])->name('plan.carry.store');
    Route::get('plans/{plan}/edit', [PlanController::class, 'edit'])->name('plan.edit');
    Route::put('plans/{plan}', [PlanController::class, 'update'])->name('plan.update');
    Route::delete('plans/{plan}', [PlanController::class, 'destroy'])->name('plan.destroy');
    Route::get('plans/{plan}/followup', [PlanController::class, 'followup'])->name('plan.followup');
    Route::post('plans/{plan}/followup', [PlanController::class, 'saveFollowup'])->name('plan.followup.save');
    Route::delete('plans/{plan}/result', [PlanController::class, 'cancelResult'])->name('plan.result.cancel');

    // Export semua tim dalam 1 shift (cetak / simpan PDF / teks WhatsApp)
    Route::get('export', [ExportController::class, 'shift'])->name('export.shift');

    // Data Activity (Daily / Weekly / Monthly) - hanya job Finish dalam 1 shift
    Route::get('data', [ActivityController::class, 'index'])->name('activity.index');
    Route::get('data/create', [ActivityController::class, 'create'])->name('activity.create');
    Route::post('data', [ActivityController::class, 'store'])->name('activity.store');
    Route::get('data/{activity}', [ActivityController::class, 'show'])->name('activity.show');
    Route::get('data/{activity}/edit', [ActivityController::class, 'edit'])->name('activity.edit');
    Route::put('data/{activity}', [ActivityController::class, 'update'])->name('activity.update');
    Route::delete('data/{activity}', [ActivityController::class, 'destroy'])->name('activity.destroy');

    // Pending / Continue (job lintas shift, tersambung lewat ID Induk)
    Route::get('pending', [PendingController::class, 'index'])->name('pending.index');
    Route::get('pending/create', [PendingController::class, 'create'])->name('pending.create');
    Route::post('pending', [PendingController::class, 'store'])->name('pending.store');
    Route::get('pending/{activity}/continue', [PendingController::class, 'continueForm'])->name('pending.continue');
    Route::post('pending/{activity}/continue', [PendingController::class, 'storeContinue'])->name('pending.continue.store');
    Route::get('pending/{activity}/timeline', [PendingController::class, 'timeline'])->name('pending.timeline');

    // Closed (Finish + Closed)
    Route::get('closed', [ClosedController::class, 'index'])->name('closed.index');

    // Import CSV / Excel (preview -> konfirmasi)
    Route::get('import', [ImportController::class, 'index'])->name('import.index');
    Route::get('import/template', [ImportController::class, 'template'])->name('import.template');
    Route::post('import/preview', [ImportController::class, 'preview'])->name('import.preview');
    Route::post('import/confirm', [ImportController::class, 'confirm'])->name('import.confirm');

    // Master data
    Route::get('master/{type?}', [MasterController::class, 'index'])->name('master.index');
    Route::post('master/{type}', [MasterController::class, 'store'])->name('master.store');
    Route::put('master/{type}/{id}', [MasterController::class, 'update'])->name('master.update');
    Route::post('master/{type}/{id}/toggle', [MasterController::class, 'toggle'])->name('master.toggle');
});
