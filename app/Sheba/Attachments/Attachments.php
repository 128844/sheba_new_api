<?php namespace App\Sheba\Attachments;

use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Sheba\Attachments\FilesAttachment;
use App\Models\Attachment;
use DB;

class Attachments
{
    use FilesAttachment;

    private $attachableType;
    private $attachableModel;
    private $model_name = 'App\Models\\';
    private $file;
    private $requestedData;
    private $createdBy;

    public function hasError($data)
    {
        if ($data->hasFile('file')) {
            return false;
        }
        return true;
    }

    public function setRequestData(Request $request)
    {
        $this->requestedData = $request;
        return $this;
    }

    public function setAttachableModel($attachable_model)
    {
        $this->attachableModel = $attachable_model;
        return $this;
    }

    public function formatData()
    {
        if ($this->requestedData->has('manager_resource')) {
            $this->createdBy = $this->requestedData->manager_resource;
        } elseif ($this->requestedData->has('manager_member')) {
            $this->createdBy = $this->requestedData->manager_member;
        }
        return $this;
    }

    public function setFile($file)
    {
        $this->file = $file;
        return $this;
    }

    public function getFile()
    {
        return $this->file;
    }

    public function store()
    {
        $data = $this->storeAttachmentToCDN($this->file);
        $attachment = new Attachment();
        try {
            DB::transaction(function () use ($attachment, $data) {
                $attachment->attachable_type = $this->model_name . class_basename($this->attachableModel);
                $attachment->attachable_id = $this->attachableModel->id;
                $attachment->title = $data['title'];
                $attachment->file = $data['file'];
                $attachment->file_type = $data['file_type'];

                $attachment->created_by = $this->createdBy->id;
                $attachment->created_by_name = $this->getName();
                $attachment->save();
            });
        } catch (QueryException $e) {
            return false;
        }
        return $attachment;
    }

    private function getName()
    {
        try {
            if ($this->createdBy->profile) {
                return $this->createdBy->profile->name;
            } else {
                return $this->attachableModel->getContactPerson();
            }

        } catch (QueryException $e) {
            return false;
        }
    }

}