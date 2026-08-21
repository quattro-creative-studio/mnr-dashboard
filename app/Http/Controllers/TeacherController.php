<?php

namespace App\Http\Controllers;

use App\Document;
use App\EditableDate;
use App\EditableEmail;
use App\Http\Repositories\SchoolClassRepository;
use App\Http\Requests\PartyGroupRegistrationRequest;
use App\Http\Requests\ProfileUpdateRequest;
use App\Http\Requests\SchoolClassCreateRequest;
use App\Http\Requests\TeacherUpdateClassRequest;
use App\Mail\CustomEmail;
use App\Mail\TeacherUpdatedClassMail;
use App\PartyGroup;
use App\Quiz;
use App\Salutation;
use App\School;
use App\SchoolClass;
use App\Setting;
use App\User;
use App\OpenedDocuments;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class TeacherController extends Controller {

    /**
     * @var SchoolClassRepository
     */
    private $classRepository;

    public function __construct(SchoolClassRepository $classRepository) {
        $this->classRepository = $classRepository;
    }

    public function profile() {
        return view('teacher.profile')->with([
            'user' => \Auth::user(),
            'teacher' => \Auth::user()->teacher,
            'salutations' => Salutation::all(),
        ]);
    }

    public function profilePost(ProfileUpdateRequest $request) {
        $data = $request->validated();
        \Auth::user()->teacher->update($data);
        \Auth::user()->update([
            'email' => $data['email'],
        ]);
        if (isset($data['password']) && $data['password'] !== null)
            \Auth::user()->updatePassword($data['password']);

        \Session::flash('message', 'Mise à jour réussie');
        return redirect()->route('teacher.profile');
    }

    function classes() {
        return view('teacher.classes-list')->with([
            'inscription_date_end' => EditableDate::find(EditableDate::TEACHER_INSCRIPTION_END)->format('d M Y'),
            'inscription_date_end_relative' => EditableDate::find(EditableDate::TEACHER_INSCRIPTION_END)->diffForHumans(),
            'classes' => $this->classRepository->findForLoggedInUser(),
            'quizzes' => Quiz::where('state', '!=', Quiz::STATE_NEW)->get(),
            'show_january' => false, // EditableDate::hasPassed(EditableDate::FOLLOW_UP_1),
            'show_march' => false, // EditableDate::hasPassed(EditableDate::FOLLOW_UP_2),
            'show_may' => EditableDate::hasPassed(EditableDate::FOLLOW_UP_3),
            'show_party' => EditableDate::hasPassed(EditableDate::FOLLOW_UP_3),
        ]);
    }

    function classesAdd() {
        if(!isRegistrationOpen()) {
            return redirect()->route('teacher.classes');
        }
        return view('teacher.classes-add')->with([
            'schools' => School::all(),
        ]);
    }

    function classesEdit(SchoolClass $class) {
        abort_if(Auth::user()->teacher->id !== $class->teacher_id, 403);
        return view('teacher.classes-edit')->with([
            'class' => $class
        ]);
    }

    function classesEditPost(TeacherUpdateClassRequest $request, SchoolClass $class) {
        abort_if(Auth::user()->teacher->id !== $class->teacher_id, 403);
        $old = [
            'name' => $class->name,
            'students' => $class->students,
        ];
        $class->update($request->validated());
        $new = [
            'name' => $class->name,
            'students' => $class->students,
        ];
        if($old != $new)
            \Mail::to(User::findByType(User::TYPE_ADMIN)->pluck('email'))
                ->queue(new TeacherUpdatedClassMail(Auth::user()->teacher, $old, $new));
        return redirect()->route('teacher.classes');
    }

    function classesAddPost(SchoolClassCreateRequest $request) {
        if(!isRegistrationOpen()) {
            return redirect()->route('teacher.classes');
        }
        $data = $request->validated();
        $teacher = \Auth::user()->teacher;
        $school = School::findOrFail($request['class_school']);
        $c = SchoolClass::createForTeacher($teacher, $data['class_name'], $data['class_students'], $school);
        \Log::info('Teacher created class: ' . $c->toJson());
        return redirect()->route('teacher.classes');
    }

    function documents() {
        return view('teacher.documents')->with([
            'documents' => Document::query()->where('visible', 1)->get()->sortBy('sort'),
        ]);
    }

    function documentsDownload(Document $document) {
        if($document->visible == true ||
            $document->visible_party == true &&
            \Auth::user()->teacher != null &&
            \Auth::user()->teacher->hasAccessToParty()) {
                \Auth::user()->openedDocuments()->firstOrCreate(['document_id' => $document->id]);
                return Storage::download($document->filename, $document->getSafeFileNameWithExtension());
        }
        return redirect()->route('teacher.documents');
    }

    function party() {
        if(!\Auth::user()->hasAccessToParty())
            return redirect()->route('teacher.classes');
        $classes = $this->classRepository->findForLoggedInUser()
            ->sort(function (SchoolClass $schoolClass) {
                return $schoolClass->partyGroups()->exists() ? 1 : 0;
            })->filter(function (SchoolClass $schoolClass) {
                return $schoolClass->isEligibleForParty();
            });
        return view('teacher.party')->with([
            'classes' => $classes,
            'documents' => Document::query()->where('visible_party', 1)->get()->sortBy('sort'),
            'open' => !Setting::isPartyClosed(),
        ]);
    }

    function partyClass(SchoolClass $class) {
        abort_if(Auth::user()->teacher->id !== $class->teacher_id, 403);

        if(!\Auth::user()->hasAccessToParty())
            return redirect()->route('teacher.classes');
        if(!$class->isEligibleForParty())
            return redirect()->route('teacher.party');
        return view('teacher.party-class')->with([
            'class' => $class,
            'groups' => $class->partyGroups,
            'open' => !Setting::isPartyClosed(),
        ]);
    }

    function partyClassPost(PartyGroupRegistrationRequest $request, SchoolClass $class) {
        abort_if(Auth::user()->teacher->id !== $class->teacher_id, 403);
        abort_if(Setting::isPartyClosed(), 403);

        if(!\Auth::user()->hasAccessToParty())
            return redirect()->route('teacher.classes');
        if(!$class->isEligibleForParty())
            return redirect()->route('teacher.party');
        $data = $request->validated()['class'];
        if (collect($data)->sum('students') > $class->students) {
            $errors = [];
            for ($i = 0; $i < sizeof($data); $i++) {
                $errors['class.'.$i.'.students'] = ['La somme des élèves des groupes doit être égale ou inférieure au nombre d\'élèves de la classe'];
            }
            throw ValidationException::withMessages($errors);
        }
        $class->partyGroups()->delete();
        for ($i = 0; $i < sizeof($data); $i++) {
            $name = $data[$i]['name'];
            $language = $data[$i]['language'];
            $students = $data[$i]['students'];
            if($name == null || $language == null || $students == null)
                continue;
            if($i >= $class->getMaxGroups()) // dont add more than allowed
                continue;
            PartyGroup::create([
                'name' => $name,
                'students' => $students,
                'language' => $language,
                'school_class_id' => $class->id,
            ]);
        }
        \Log::info('Teacher ' . $class->teacher->full_name . ' registered ' . sizeof($data) . ' groups to the final party');
        return redirect()->route('teacher.party');
    }

    public function deleteParty(SchoolClass $class) {
        abort_if(Auth::user()->teacher->id !== $class->teacher_id, 403);
        $class->partyGroups()->delete();
        return redirect()->route('teacher.party');
    }

}
