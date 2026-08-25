<?php
namespace App\Http\Repositories;

use App\Certificate;

class CertificateRepository {

    public function getByUid($uid): ?Certificate {
        return Certificate::query()
            ->where('uid', $uid)
            ->first();
    }

}