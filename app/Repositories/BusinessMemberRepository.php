<?php

namespace App\Repositories;


class BusinessMemberRepository
{

    public function isBusinessMember($business, $id)
    {
        return $business->members()->where('members.id', $id)->first();
    }

    public function getInfo($member)
    {
        array_forget($member, 'is_verified');
        array_forget($member, 'created_by');
        array_forget($member, 'created_by_name');
        array_forget($member, 'updated_by');
        array_forget($member, 'updated_by_name');
        array_forget($member, 'created_at');
        array_forget($member, 'updated_at');
        array_forget($member, 'remember_token');
        return $member;
    }
}