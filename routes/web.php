<?php

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

use App\Certificate;

Route::redirect('/', '/login');

Route::get('/logout', 'Auth\LoginController@logout')->name('logout');
Route::get('/login', 'Auth\LoginController@showLoginForm')->name('login');
Route::post('/login', 'Auth\LoginController@login')->name('login.post');
Route::get('/login/recover', 'Auth\ForgotPasswordController@showLinkRequestForm')->name('login.password.recover');
Route::post('/login/recover', 'Auth\ForgotPasswordController@sendResetLinkEmail')->name('login.password.recover.post');
Route::get('/login/recover/reset/{token}', 'Auth\ResetPasswordController@showResetForm')->name('login.password.reset');
Route::post('/login/recover/reset', 'Auth\ResetPasswordController@reset')->name('login.password.reset.post');
Route::get('/login/redirect', 'Auth\LoginController@loginRedirect')->name('login.redirect');

Route::get('/external/classes', 'ExternalController@classes')->name('external.classes');
Route::get('/external/quiz/redirect/{quizCode}', 'QuizController@redirect')->name('external.quiz.redirect');
Route::get('/external/quiz/{uuid}', 'QuizController@showQuizRedirect')->name('external.quiz.show');

Route::group(['middleware' => 'guest'], function () {
    Route::get('/teacher/register', 'TeacherRegisterController@start')->name('teacher.register');
    Route::post('/teacher/register', 'TeacherRegisterController@startPost')->name('teacher.registerPost');
});
Route::group(['middleware' => ['auth', 'teacher']], function () {
    Route::get('/teacher/profile', 'TeacherController@profile')->name('teacher.profile');
    Route::post('/teacher/profile', 'TeacherController@profilePost')->name('teacher.profile.post');
    Route::get('/teacher/classes', 'TeacherController@classes')->name('teacher.classes');
    Route::get('/teacher/classes/{class}/edit', 'TeacherController@classesEdit')->name('teacher.classes.edit');
    Route::post('/teacher/classes/{class}/edit', 'TeacherController@classesEditPost')->name('teacher.classes.edit.post');
    Route::get('/teacher/classes/add', 'TeacherController@classesAdd')->name('teacher.classes.add');
    Route::post('/teacher/classes/add', 'TeacherController@classesAddPost')->name('teacher.classes.add.post');
    Route::get('/teacher/documents', 'TeacherController@documents')->name('teacher.documents');
    Route::get('/teacher/documents/download/{document}', 'TeacherController@documentsDownload')->name('teacher.documents.download');
    Route::get('/teacher/party', 'TeacherController@party')->name('teacher.party');
    Route::get('/teacher/party/{class}', 'TeacherController@partyClass')->name('teacher.party.class');
    Route::post('/teacher/party/{class}', 'TeacherController@partyClassPost')->name('teacher.party.class.post');
    Route::delete('/teacher/party/{class}/delete', 'TeacherController@deleteParty')->name('teacher.party.class.delete');
    Route::get('/teacher/quizzes', '\App\Http\Controllers\Teachers\QuizIndexController')->name('teacher.quizzes');
});

Route::get('/suivi/response/{class}/{stillNonSmoking}', 'FollowUpController@showFollowUpResponse')->name('follow-up-response');
Route::get('/suivi/{token}/{stillNonSmoking}', 'FollowUpController@setFollowUpStatus')->name('follow-up');
Route::get('/party/{token}/{status}', 'PartyController@handlePartyResponse')->name('party-response');

Route::get('/certificat/{certificate_uid}', 'CertificateController@downloadPage')->name('certificate.page');
Route::get('/certificat/download/{certificate_uid}', 'CertificateController@downloadCertificate')->name('certificate.download');

Route::group(['middleware' => ['auth', 'admin']], function () {;
    Route::group(['namespace' => 'Admin'], function () {
        Route::get('/admin/classes', 'SchoolClassController@classes')->name('admin.classes');
        Route::post('/admin/classes/resend/{status}', 'SchoolClassController@resend')->name('admin.classes.resend');
        Route::get('/admin/classes/export', 'ClassExportController@export')->name('admin.classes.export');
        Route::delete('/admin/classes/{class}/delete', 'SchoolClassController@delete')->name('admin.classes.delete');
        Route::get('/admin/classes/{class}', 'SchoolClassController@classesEdit')->name('admin.classes.edit');
        Route::post('/admin/classes/{class}', 'SchoolClassController@classesEditPost')->name('admin.classes.edit.post');

        Route::get('/admin/schools', 'SchoolController@schools')->name('admin.schools');
        Route::get('/admin/schools/{school}', 'SchoolController@schoolsEdit')->name('admin.schools.edit');
        Route::post('/admin/schools/{school}', 'SchoolController@schoolsEditPost')->name('admin.schools.edit.post');

        Route::get('/admin/certificates', 'CertificateController@index')->name('admin.certificates');
        Route::post('/admin/certificates/send', 'CertificateController@sendEmail')->name('admin.certificates.send');
        Route::post('/admin/certificates/generate/all', 'CertificateController@generateAll')->name('admin.certificates.generate.all');
        Route::post('/admin/certificates/generate/missing', 'CertificateController@generateMissing')->name('admin.certificates.generate.missing');
        Route::post('/admin/certificates/generate/{class}', 'CertificateController@generate')->name('admin.certificates.generate');
        Route::delete('/admin/certificates/delete/{certificate}', 'CertificateController@delete')->name('admin.certificates.delete');
        Route::get('/admin/certificates/{certificate}', 'CertificateController@download')->name('admin.certificates.download');

        Route::get('/admin/teachers', 'TeacherController@teachers')->name('admin.teachers');
        Route::get('/admin/teachers/{teacher}', 'TeacherController@teachersEdit')->name('admin.teachers.edit');
        Route::post('/admin/teachers/{teacher}', 'TeacherController@teachersEditPost')->name('admin.teachers.edit.post');

        Route::get('/admin/emails', 'EmailController@emails')->name('admin.emails');
        Route::get('/admin/emails/{email}', 'EmailController@emailsEdit')->name('admin.emails.edit');
        Route::post('/admin/emails/{email}', 'EmailController@emailsEditPost')->name('admin.emails.edit.post');

        Route::get('/admin/dates', 'EditableDateController@index')->name('admin.dates');
        Route::post('/admin/dates', 'EditableDateController@update')->name('admin.dates.post');

        Route::get('/admin/documents', 'DocumentController@documents')->name('admin.documents');
        Route::post('/admin/documents', 'DocumentController@documentsPost')->name('admin.documents.post');
        Route::post('/admin/documents/{document}/visibility', 'DocumentController@documentsToggleVisibility')->name('admin.documents.toggleVisibility');
        Route::get('/admin/documents/{document}/edit', 'DocumentController@edit')->name('admin.documents.edit');
        Route::post('/admin/documents/{document}/edit', 'DocumentController@editUpdate')->name('admin.documents.update');
        Route::get('/admin/documents/{document}/download', 'DocumentController@documentsDownload')->name('admin.documents.download');
        Route::delete('/admin/documents/{document}/delete', 'DocumentController@documentsDelete')->name('admin.documents.delete');
        Route::get('/admin/documents/{document}/moveUp', 'DocumentController@moveUp')->name('admin.documents.moveUp');
        Route::get('/admin/documents/{document}/moveDown', 'DocumentController@moveDown')->name('admin.documents.moveDown');

        Route::get('/admin/quiz', 'QuizController@index')->name('admin.quiz');
        Route::get('/admin/quiz/new', 'QuizController@create')->name('admin.quiz.create');
        Route::post('/admin/quiz/new', 'QuizController@createPost')->name('admin.quiz.create.post');
        Route::get('/admin/quiz/{quiz}', 'QuizController@show')->name('admin.quiz.show');
        Route::get('/admin/quiz/{quiz}/edit', 'QuizController@edit')->name('admin.quiz.edit');
        Route::post('/admin/quiz/{quiz}/edit', 'QuizController@editPost')->name('admin.quiz.edit.post');
        Route::get('/admin/quiz/{quiz}/codes', 'QuizController@codes')->name('admin.quiz.show.codes');
        Route::post('/admin/quiz/{quiz}/codes', 'QuizController@createCodes')->name('admin.quiz.show.codes.post');
        Route::get('/admin/quiz/{quiz}/review', 'QuizController@review')->name('admin.quiz.review');
        Route::get('/admin/quiz/{quiz}/review-mail', 'QuizController@previewMail')->name('admin.quiz.review-mail');
        Route::post('/admin/quiz/{quiz}/send', 'QuizController@send')->name('admin.quiz.send');
        Route::post('/admin/quiz/{quiz}/send-reminder', 'QuizController@sendReminders')->name('admin.quiz.send-reminder');
        Route::delete('/admin/quiz/{quiz}/delete', 'QuizController@delete')->name('admin.quiz.delete');

        Route::get('/admin/party', 'PartyController@party')->name('admin.party');
        Route::get('/admin/party/export', 'PartyExportController@export')->name('admin.party.export');
        Route::get('/admin/party/{class}', 'PartyController@partyClass')->name('admin.party.class');
        Route::post('/admin/party/{class}', 'PartyController@partyClassPost')->name('admin.party.class.post');
        Route::delete('/admin/party/{group}/delete', 'PartyController@deleteGroup')->name('admin.party.class.delete');

        Route::get('/admin/users', 'UserController@users')->name('admin.users');
        Route::get('/admin/users/add', 'UserController@usersAdd')->name('admin.users.add');
        Route::post('/admin/users/add', 'UserController@usersAddPost')->name('admin.users.add.post');
        Route::post('/admin/users/{user}/resend', 'UserController@usersResend')->name('admin.users.resend');
        Route::delete('/admin/users/{user}', 'UserController@usersDelete')->name('admin.users.delete');

        // Settings
        Route::get('/settings', 'SettingsController@index')->name('admin.settings');
        Route::post('/settings', 'SettingsController@update')->name('admin.settings.post');
    });
});
