<?php


namespace App\Http\Repositories;


use App\EditableEmail;
use App\SchoolClass;

class EmailRepository {

    /**
     * @param string $status
     * @return array
     * @throws \Exception
     */
    public function findFollowUpForStatus(string $status): array {
        if($status == SchoolClass::STATUS_JANUARY)
            return EditableEmail::$MAIL_FOLLOW_UP_1;
        if($status == SchoolClass::STATUS_MARCH)
            return EditableEmail::$MAIL_FOLLOW_UP_2;
        if($status == SchoolClass::STATUS_MAY)
            return EditableEmail::$MAIL_FOLLOW_UP_3;
        throw new \Exception("no follow up mail exists for status: $status");
    }

    /**
     * @param string $status
     * @return array
     * @throws \Exception
     */
    public function findFollowUpReminderForStatus(string $status): array {
        if($status == SchoolClass::STATUS_JANUARY)
            return EditableEmail::$MAIL_FOLLOW_UP_1_REMINDER;
        if($status == SchoolClass::STATUS_MARCH)
            return EditableEmail::$MAIL_FOLLOW_UP_2_REMINDER;
        if($status == SchoolClass::STATUS_MAY)
            return EditableEmail::$MAIL_FOLLOW_UP_3_REMINDER;
        throw new \Exception("no follow up mail exists for status: $status");
    }

    /**
     * @param string $status
     * @return array
     * @throws \Exception
     */
    public function findFollowUpResponseNegativeForStatus(string $status): array {
        if($status == SchoolClass::STATUS_JANUARY)
            return EditableEmail::$MAIL_FOLLOW_UP_1_NO;
        if($status == SchoolClass::STATUS_MARCH)
            return EditableEmail::$MAIL_FOLLOW_UP_2_NO;
        if($status == SchoolClass::STATUS_MAY)
            return EditableEmail::$MAIL_FOLLOW_UP_3_NO;
        throw new \Exception("no follow up mail exists for status: $status");
    }

    /**
     * @param string $status
     * @return array
     * @throws \Exception
     */
    public function findFollowUpResponsePositiveForStatus(string $status): array {
        if($status == SchoolClass::STATUS_JANUARY)
            return EditableEmail::$MAIL_FOLLOW_UP_1_YES;
        if($status == SchoolClass::STATUS_MARCH)
            return EditableEmail::$MAIL_FOLLOW_UP_2_YES;
        if($status == SchoolClass::STATUS_MAY)
            return EditableEmail::$MAIL_INVITE_PARTY;
        throw new \Exception("no follow up mail exists for status: $status");
    }

    /**
     * @return EditableEmail
     */
    public function findPartyResponseNegative(): EditableEmail {
        return EditableEmail::find(EditableEmail::$MAIL_INVITE_PARTY_NO);
    }

}