<?php

namespace App\Repositories\Eloquent;

use Exception;
use App\Models\Action;
use App\Models\Media;
use App\Models\Activity;
use App\Traits\Common\UploadableTrait;
use App\Repositories\Contracts\ActionInterface;
use App\Exceptions\Api\UnknowException;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class ActionRepository extends BaseRepository implements ActionInterface
{
    use UploadableTrait;

    public function model()
    {
        return Action::class;
    }

    public function createOrDeleteLike($action, $userId)
    {
        if (!is_numeric($userId) || !$action) {
            return false;
        }

        if ($action->likes->where('user_id', $userId)->isEmpty()) {
            return $this->createByRelationship('likes', [
                'model' => $action,
                'attribute' => ['user_id' => $userId],
            ]);
        }

        return $action->likes()->where('user_id', $userId)->first()->forceDelete();
    }

    public function update($action, $inputs)
    {
        if (!empty($inputs['upload'])) {
            $result = $this->makeDataMedias($inputs['upload']);
            $action->media()->createMany($result);
        }

        return parent::update($action->id, $inputs['data_action']);
    }

    public function create($inputs)
    {
        $action = parent::create($inputs['data_action']);
        $action->activities()->create([
            'user_id' => $inputs['data_action']['user_id'],
            'name' => Activity::CREATE,
        ]);

        if (!is_null($inputs['upload'])) {
            $media = $this->createDataMedias($inputs['upload']);
            $action->media()->createMany($media);
        }

        return true;
    }

    public function getActionPaginate($action)
    {
        return $action
            ->with('user', 'likes', 'comments', 'media')
            ->orderBy('created_at', 'DESC')
            ->paginate(config('settings.actions.paginate_in_event'));
    }

    public function searchAction($eventId, $key)
    {
        return $this->model
            ->with('user', 'likes', 'comments', 'media')
            ->where('event_id', $eventId)
            ->search($key, null, true)
            ->paginate(config('settings.actions.paginate_in_event'));
    }
}
