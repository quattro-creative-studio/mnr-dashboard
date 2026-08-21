<?php

namespace App\Http\Controllers\Admin;

use App\EditableDate;
use App\Http\Controllers\Controller;
use App\Http\Controllers\FollowUpController;
use App\Http\Managers\SchoolClassManager;
use App\Http\Requests\AdminClassUpdateRequest;
use App\Quiz;
use App\School;
use App\SchoolClass;
use App\Teacher;
use Carbon\Carbon;
use Illuminate\Support\Facades\Session;

class SchoolClassController extends Controller {

    /**
     * @var SchoolClassManager
     */
    private $classManager;

    /**
     * @var FollowUpController
     */
    private $followUpController;

    public function __construct(SchoolClassManager $classManager, FollowUpController $followUpController) {
        $this->classManager = $classManager;
        $this->followUpController = $followUpController;
    }

    public function classes() {
        return view('admin.classes')->with([
            'quizzes' => Quiz::all(),
            'classes' => SchoolClass::all()->load('teacher'),
            'show_january' => EditableDate::hasPassed(EditableDate::FOLLOW_UP_1),
            'show_march' => EditableDate::hasPassed(EditableDate::FOLLOW_UP_2),
            'show_may' => EditableDate::hasPassed(EditableDate::FOLLOW_UP_3),
        ]);
    }

    public function classesEdit(SchoolClass $class) {
        return view('admin.classes-edit')->with([
            'class' => $class,
            'schools' => School::all(),
            'teachers' => Teacher::all(),
            'show_january' => EditableDate::hasPassed(EditableDate::FOLLOW_UP_1),
            'show_march' => EditableDate::hasPassed(EditableDate::FOLLOW_UP_2),
            'show_may' => EditableDate::hasPassed(EditableDate::FOLLOW_UP_3),
            'show_party' => EditableDate::hasPassed(EditableDate::FOLLOW_UP_3),
        ]);
    }

    public function classesEditPost(AdminClassUpdateRequest $request, SchoolClass $class) {
        $class->update($request->validated());
        Session::flash('message', 'Mise à jour réussie');
        return redirect()->route('admin.classes.edit', [$class]);
    }

    public function resend($status) {
        $numSent = SchoolClass::all()
            ->map(function (SchoolClass $class) use ($status) {
                if ($this->classManager->shouldSendFollowUpReminder($class, $status, false)) {
                    $this->followUpController->sendFollowUpReminderMail($class, $status);
                    return 1;
                }
                return 0;
            })
            ->sum();
        Session::flash('message', 'Envoyé ' . $numSent . ' Emails');
        return redirect()->route('admin.classes');
    }

    public function delete(SchoolClass $class) {
        $name = $class->name;
        $class->delete();
        Session::flash('message', 'Supprimé la classe ' . $name);
        return redirect()->route('admin.classes');
    }

}
