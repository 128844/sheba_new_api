<?php namespace Sheba;

class RequestIdentification
{
    use ModificationFields;

    /**
     * Merge the data with only user agent modification fields.
     *
     * @param $data
     * @return array
     */
    public function set($data)
    {
        return array_merge($data, $this->get());
    }

    public function get()
    {
        $created_by_type = $this->getModifierType();
        return [
            'portal_name' => request()->hasHeader('Portal-Name') ? request()->header('Portal-Name') : (!is_null(request('portal_name')) ? request('portal_name') : config('sheba.portal')),
            'ip' => !is_null(request('ip')) ? request('ip') : request()->ip(),
            'user_agent' => !is_null(request('user_agent')) ? request('user_agent') : request()->header('User-Agent'),
            'created_by_type' => $created_by_type ? $created_by_type : 'automatic'
        ];
    }
}