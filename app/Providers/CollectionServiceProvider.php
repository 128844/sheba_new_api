<?php namespace App\Providers;

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\ServiceProvider;

class CollectionServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the application services.
     *
     * @return void
     */
    public function boot()
    {
        Collection::macro('toAssoc', function () {
            return $this->reduce(function ($assoc, $keyValuePair) {
                list($key, $value) = $keyValuePair;
                $assoc[$key] = $value;
                return $assoc;
            }, collect([]));
        });

        Collection::macro('toAssocFromKey', function (callable $callback) {
            return $this->reduce(function ($assoc, $value) use ($callback) {
                $key = $callback($value);
                $assoc[$key] = $value;
                return $assoc;
            }, new static);
        });

        Collection::macro('mapToAssoc', function ($callback) {
            return $this->map($callback)->toAssoc();
        });

        Collection::macro('pluckMultiple', function ($assoc, $key, $is_object = false) {
            return $this->mapToAssoc(function ($item) use ($key, $assoc, $is_object) {
                $list = [];
                $key = data_get($item, $key);
                foreach ($assoc as $key2) {
                    $list[$key2] = data_get($item, $key2);
                }
                if($is_object) $list = json_decode(json_encode($list));
                return [$key, $list];
            });
        });

        Collection::macro('findByKey', function ($key, $value) {
            return collect($this->items)->where($key, $value)->first();
        });

        Collection::macro('findById', function ($id) {
            return collect($this->items)->findByKey('id', $id);
        });

        Collection::macro('uppercase', function() {
            return collect($this->items)->map(function($word) {
                return strtoupper($word);
            });
        });

        Collection::macro('mapWithKeys', function (callable $callback) {
            $result = [];

            foreach ($this->items as $key => $value) {
                $assoc = $callback($value, $key);

                foreach ($assoc as $mapKey => $mapValue) {
                    $result[$mapKey] = $mapValue;
                }
            }

            return new static($result);
        });

        /*
         * use Illuminate\Support\Collection;
         * use Illuminate\Pagination\LengthAwarePaginator;
         *
         * Paginate a standard Laravel Collection.
         *
         * @param int $perPage
         * @param int $total
         * @param int $page
         * @param string $pageName
         * @return array
         */
        Collection::macro('paginate', function($perPage, $total = null, $page = null, $pageName = 'page') {
            $page = $page ?: LengthAwarePaginator::resolveCurrentPage($pageName);
            return new LengthAwarePaginator(
                $this->forPage($page, $perPage),
                $total ?: $this->count(),
                $perPage,
                $page,
                [
                    'path' => LengthAwarePaginator::resolveCurrentPath(),
                    'pageName' => $pageName,
                ]
            );
        });

        Collection::macro('forgetEach', function($key) {
            return collect($this->items)->map(function ($item) use ($key) {
                if (array_key_exists($key, $item)) unset($item[$key]);
                return $item;
            });
        });
    }

    /**
     * Register the application services.
     *
     * @return void
     */
    public function register()
    {
        //
    }
}
