<?php
namespace App\Http\Controllers\Admin;

use App\EditableDate;
use App\EditableEmail;
use App\Http\Requests\AdminDateUpdateRequest;
use App\Http\Requests\AdminEmailsUpdateRequest;
use App\PlaceHolder;
use Carbon\Carbon;
use Illuminate\Support\Facades\Session;

class EmailController {

    public function emails() {
        // 'emails' => EditableEmail::all()->sortBy(function (EditableEmail $editableEmail) {
        //     if($editableEmail->dates()->first() == null)
        //         return Carbon::maxValue()->timestamp;
        //     return $editableEmail->dates()->first()->value->timestamp;
        // }),
        $all = EditableEmail::query()->with('dates')->orderBy('sort_order')->get();

        // Production simply deleted the retired follow-up mails. Here they are
        // kept -- they come back when the mechanism does -- but folded away, so
        // the list an administrator reads is the list that actually goes out.
        $emails = $all->reject->isDormant()->values();
        $unusedEmails = $all->filter->isDormant()->values();

        // The list shows one row per mail, and only a mail the calendar sends
        // gets its date edited in that row. Every other date goes to the block
        // below, so nothing becomes uneditable: "Début inscriptions" is linked
        // to the confirmation mail for its heading, but it is really the date
        // isRegistrationOpen() reads to open the public form.
        $scheduled = $emails->filter->isScheduled()->flatMap->dates->pluck('key');

        // 'dates' => EditableDate::query()->orderBy('value')->get(),
        $dates = EditableDate::query()->orderBy('sort_order')->get();

        return view('admin.emails')->with([
            'emails' => $emails,
            'unusedEmails' => $unusedEmails,
            'otherDates' => $dates->whereNotIn('key', $scheduled)->values(),
        ]);
    }

    /**
     * Switch a scheduled mail on or off for this edition.
     *
     * Only the calendar honours the flag. A transactional mail is sent by an
     * action and a dormant one by nothing at all, so in both cases the flag
     * would be stored and never read -- refuse instead of letting an
     * administrator believe an envoi has been stopped.
     */
    public function toggle(EditableEmail $email) {
        if (!$email->isScheduled()) {
            Session::flash('error', $email->isTransactional()
                ? "« {$email->title} » part au moment de l'inscription, pas par le calendrier : il ne peut pas être désactivé ici."
                : "« {$email->title} » n'est envoyé par aucun automatisme cette année : il n'y a rien à désactiver.");

            return redirect()->route('admin.emails');
        }

        $email->update(['enabled' => !$email->enabled]);

        Session::flash('message', $email->enabled
            ? "« {$email->title} » est réactivé et repartira à la date indiquée."
            : "« {$email->title} » est désactivé : il ne partira pas, sa date est conservée.");

        return redirect()->route('admin.emails');
    }

    public function emailsEdit(EditableEmail $email) {
        return view('admin.emails-edit')->with([
            'email' => $email,
            'placeholders' => self::getPlaceholdersForView(),
        ]);
    }

    public function emailsEditPost(AdminEmailsUpdateRequest $request, EditableEmail $email) {
        $email->update($request->validated());
        Session::flash('message', 'Mise à jour réussie');
        return redirect()->route('admin.emails');
    }

    public static function getPlaceholdersForView() {
        return PlaceHolder::getPlaceholders()->map(function (PlaceHolder $placeHolder) {
            return [
                'type' => 'choiceitem',
                'text' => !empty($placeHolder->description) ? $placeHolder->description : $placeHolder->previewValue,
                'preview' => $placeHolder->previewValue,
                'value' => $placeHolder->key,
            ];
        });
    }

}
