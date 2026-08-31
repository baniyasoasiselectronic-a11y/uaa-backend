<?php

use App\Http\Controllers\Api\Account\AccountController;
use App\Http\Controllers\Api\Account\FavoriteController;
use App\Http\Controllers\Api\Auth\AuthController;
use App\Http\Controllers\Api\BuildingController;
use App\Http\Controllers\Api\CallbackController;
use App\Http\Controllers\Api\CareerController;
use App\Http\Controllers\Api\CommunityController;
use App\Http\Controllers\Api\ComplaintController;
use App\Http\Controllers\Api\ContactController;
use App\Http\Controllers\Api\EnquiryController;
use App\Http\Controllers\Api\ListWithUsController;
use App\Http\Controllers\Api\LookupController;
use App\Http\Controllers\Api\NeighborhoodController;
use App\Http\Controllers\Api\NewsController;
use App\Http\Controllers\Api\OpenHouseController;
use App\Http\Controllers\Api\PropertyController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public read endpoints
|--------------------------------------------------------------------------
*/
Route::get('properties', [PropertyController::class, 'index'])->name('properties.index');
Route::get('properties/featured', [PropertyController::class, 'featured'])->name('properties.featured');
Route::get('properties/{slug}', [PropertyController::class, 'show'])->name('properties.show');

Route::get('communities', [CommunityController::class, 'index'])->name('communities.index');
Route::get('communities/{slug}', [CommunityController::class, 'show'])->name('communities.show');

Route::get('buildings', [BuildingController::class, 'index'])->name('buildings.index');
Route::get('buildings/{slug}', [BuildingController::class, 'show'])->name('buildings.show');

Route::get('neighborhoods', [NeighborhoodController::class, 'index'])->name('neighborhoods.index');
Route::get('neighborhoods/{slug}', [NeighborhoodController::class, 'show'])->name('neighborhoods.show');

Route::get('news', [NewsController::class, 'index'])->name('news.index');
Route::get('news/{slug}', [NewsController::class, 'show'])->name('news.show');

Route::get('open-houses', [OpenHouseController::class, 'index'])->name('open-houses.index');
Route::get('open-houses/{slug}', [OpenHouseController::class, 'show'])->name('open-houses.show');

Route::get('careers', [CareerController::class, 'index'])->name('careers.index');
Route::get('careers/{slug}', [CareerController::class, 'show'])->name('careers.show');

Route::get('reviews', [\App\Http\Controllers\Api\ReviewController::class, 'index'])->name('reviews.index');
Route::get('property-types', [LookupController::class, 'propertyTypes'])->name('property-types.index');
Route::get('amenities', [LookupController::class, 'amenities'])->name('amenities.index');

/*
|--------------------------------------------------------------------------
| Public write endpoints (rate limited)
|--------------------------------------------------------------------------
*/
Route::middleware('throttle:20,1')->group(function () {
    Route::post('enquiries', [EnquiryController::class, 'store'])->name('enquiries.store');
    Route::post('contact-requests', [ContactController::class, 'store'])->name('contact.store');
    Route::post('callback-requests', [CallbackController::class, 'store'])->name('callback.store');
    Route::post('complaints', [ComplaintController::class, 'store'])->name('complaints.store');
    Route::post('list-with-us-requests', [ListWithUsController::class, 'store'])->name('list-with-us.store');
    Route::post('open-houses/{openHouse}/register', [OpenHouseController::class, 'register'])->name('open-houses.register');
    Route::post('careers/{career}/apply', [CareerController::class, 'apply'])->name('careers.apply');
    Route::post('job-applications', [\App\Http\Controllers\Api\JobApplicationController::class, 'store'])->name('job-applications.store');
    Route::post('call-leads', [\App\Http\Controllers\Api\CallLeadController::class, 'store'])->name('call-leads.store');
});

/*
|--------------------------------------------------------------------------
| Authentication
|--------------------------------------------------------------------------
*/
Route::middleware('throttle:10,1')->group(function () {
    Route::post('auth/register', [AuthController::class, 'register'])->name('auth.register');
    Route::post('auth/login', [AuthController::class, 'login'])->name('auth.login');
});

/*
|--------------------------------------------------------------------------
| Authenticated customer portal
|--------------------------------------------------------------------------
*/
Route::middleware('auth:sanctum')->group(function () {
    Route::post('auth/logout', [AuthController::class, 'logout'])->name('auth.logout');
    Route::get('auth/user', [AuthController::class, 'user'])->name('auth.user');

    Route::get('account/favorites', [FavoriteController::class, 'index'])->name('account.favorites.index');
    Route::post('account/favorites/{property}', [FavoriteController::class, 'store'])->name('account.favorites.store');
    Route::delete('account/favorites/{property}', [FavoriteController::class, 'destroy'])->name('account.favorites.destroy');

    Route::get('account/enquiries', [AccountController::class, 'enquiries'])->name('account.enquiries');
    Route::get('account/complaints', [AccountController::class, 'complaints'])->name('account.complaints');
});
