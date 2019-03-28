<?php namespace App\Providers;

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