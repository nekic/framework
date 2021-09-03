<?php

namespace Litepie\Repository;

use Illuminate\Container\Container as Application;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Litepie\Repository\Exceptions\RepositoryException;
use Litepie\Repository\Interfaces\FilterInterface;
use Litepie\Repository\Interfaces\RepositoryInterface;
use Litepie\Repository\Presenter\Presenter;

/**
 * Class BaseRepository.
 *
 * @author Renfos Technologies Pvt. Ltd. <info@info@renfos.com>
 */
abstract class BaseRepository implements RepositoryInterface
{
    /**
     * @var Application
     */
    protected $app;

    /**
     * @var Model
     */
    public $model;

    /**
     * @var Collection
     */
    public $result;

    /**
     * @var array
     */
    protected $fieldSearchable = [];

    /**
     * @var Presenter
     */
    protected $presenter;

    /**
     * Collection of Filter
     *
     * @var Collection
     */
    protected $filters;

    /**
     * @var bool
     */
    protected $skipFilter = false;

    /**
     * @var bool
     */
    protected $skipPresenter = false;

    /**
     * @param Application $app
     */
    public function __construct(Application $app)
    {
        $this->app = $app;
        $this->filter = new Collection();
        $this->makeModel();
        $this->makePresenter();
        $this->boot();
    }

    /**
     *
     */
    public function boot()
    {
        //
    }

    /**
     * @throws RepositoryException
     */
    public function resetModel()
    {
        $this->makeModel();
    }

    /**
     * Specify Model class name
     *
     * @return string
     */
    abstract public function model();

    /**
     * Specify Presenter class name
     *
     * @return string
     */
    public function presenter()
    {
        return null;
    }

    /**
     * Specify Presenter class name
     *
     * @return string
     */
    public function __get(string $name)
    {
        return $this->model->$name;
    }

    /**
     * Set Presenter
     *
     * @param $presenter
     *
     * @return $this
     */
    public function setPresenter($presenter)
    {

        $this->makePresenter($presenter);

        return $this;
    }

    /**
     * @return Model
     * @throws RepositoryException
     */
    public function makeModel()
    {
        $model = $this->app->make($this->model());

        if (!$model instanceof Model) {
            throw new RepositoryException("Class {$this->model()} must be an instance of Illuminate\\Database\\Eloquent\\Model");
        }

        return $this->model = $model;
    }

    /**
     * @param null $presenter
     *
     * @return Presenter
     * @throws RepositoryException
     */
    public function makePresenter($presenter = null)
    {

        $presenter = !is_null($presenter) ? $presenter : $this->presenter();
        if (!is_null($presenter)) {

            $this->presenter = $presenter;

            if (!is_subclass_of($this->presenter, Presenter::class)) {
                throw new RepositoryException("Class {$presenter} must be an instance of Litepie\Repository\Presenter\Presenter");
            }

            return $this->presenter;
        }

        return null;
    }

    /**
     * Get Searchable Fields
     *
     * @return array
     */
    public function getFieldsSearchable()
    {
        return $this->fieldSearchable;
    }

    /**
     * Query Scope
     *
     * @param \Closure $scope
     *
     * @return $this
     */
    public function scopeQuery(\Closure $scope)
    {
        $this->scopeQuery = $scope;

        return $this;
    }

    /**
     * Retrieve all data of repository, simple paginated
     *
     * @param null $limit
     * @param array $columns
     *
     * @return mixed
     */
    public function toArray()
    {
        if (is_null($this->result)) {
            if (!($this->model instanceof Model)){
                return null;
            }
            $this->result = $this->model;
        }
        $this->resetModel();

        return $this->parserResult($this->result)->toArray();
    }

    /**
     * Retrieve all data of repository, simple paginated
     *
     * @param null $limit
     * @param array $columns
     *
     * @return mixed
     */
    public function toJson()
    {
        if (is_null($this->result)) {
            if (!($this->model instanceof Model)){
                return null;
            }
            $this->result = $this->model;
        }
        $this->resetModel();

        return $this->parserResult($this->result)->toJson();
    }

    /**
     * Push Filter for filter the query
     *
     * @param $filter
     *
     * @return $this
     * @throws \Prettus\Repository\Exceptions\RepositoryException
     */
    public function pushFilter($filter)
    {
        if (is_string($filter)) {
            $filter = new $filter;
        }
        if (!$filter instanceof FilterInterface) {
            throw new RepositoryException("Class " . $filter . " must be an instance of Litepie\\Repository\\Interfaces\\FilterInterface");
        }

        $this->filter->push($filter);

        return $this;
    }

    /**
     * Pop Filter
     *
     * @param $filter
     *
     * @return $this
     */
    public function popFilter($filter)
    {
        $this->filter = $this->filter->reject(function ($item) use ($filter) {
            if (is_object($item) && is_string($filter)) {
                return get_class($item) === $filter;
            }

            if (is_string($item) && is_object($filter)) {
                return $item === get_class($filter);
            }

            return get_class($item) === get_class($filter);
        });

        return $this;
    }

    /**
     * Get Collection of Filter
     *
     * @return Collection
     */
    public function getFilter()
    {
        return $this->filter;
    }

    /**
     * Find data by Filter
     *
     * @param FilterInterface $filter
     *
     * @return mixed
     */
    public function getByFilter(FilterInterface $filter)
    {
        $this->model = $filter->apply($this->model, $this);
        $this->result = $this->model->get();
        return $this;
    }

    /**
     * Skip Filter
     *
     * @param bool $status
     *
     * @return $this
     */
    public function skipFilter($status = true)
    {
        $this->skipFilter = $status;

        return $this;
    }

    /**
     * Reset all Filters
     *
     * @return $this
     */
    public function resetFilter()
    {
        $this->filter = new Collection();

        return $this;
    }

    /**
     * Reset Query Scope
     *
     * @return $this
     */
    public function resetScope()
    {
        $this->scopeQuery = null;

        return $this;
    }

    /**
     * Apply scope in current Query
     *
     * @return $this
     */
    protected function applyScope()
    {
        if (isset($this->scopeQuery) && is_callable($this->scopeQuery)) {
            $callback = $this->scopeQuery;
            $this->model = $callback($this->model);
        }

        return $this;
    }

    /**
     * Apply filter in current Query
     *
     * @return $this
     */
    protected function applyFilter()
    {

        if ($this->skipFilter === true) {
            return $this;
        }
        $filters = $this->getFilter();
        if ($filters) {
            foreach ($filters as $filter) {
                if (is_subclass_of($filter, FilterInterface::class)) {
                    $this->model = $filter->apply($this->model, $this);
                }
            }
        }
        return $this;
    }

    /**
     * Applies the given where conditions to the model.
     *
     * @param array $where
     * @return void
     */
    protected function applyConditions(array $where)
    {
        foreach ($where as $field => $value) {
            if (is_array($value)) {
                list($field, $condition, $val) = $value;
                $this->model = $this->model->where($field, $condition, $val);
            } else {
                $this->model = $this->model->where($field, '=', $value);
            }
        }
    }

    /**
     * Skip Presenter Wrapper
     *
     * @param bool $status
     *
     * @return $this
     */
    public function skipPresenter($status = true)
    {
        $this->skipPresenter = $status;

        return $this;
    }

    /**
     * Wrapper result data
     *
     * @param mixed $result
     *
     * @return mixed
     */
    public function parserResult($result)
    {
        if ($this->presenter && !$this->skipPresenter) {
            if (is_subclass_of($result, LengthAwarePaginator::class)) {
                $result = $this->presenter::present($result);
            }

            if (is_subclass_of($result, Paginator::class)) {
                $result = $this->presenter::present($result);
            }

            if (is_a($result, Collection::class)) {
                $result = $this->presenter::present($result);
            }

            if (is_a($result, Model::class)) {
                $result = $this->presenter::make($result);
            }
        }

        return $result;
    }

    /**
     * @param $method
     * @param $args
     * @return mixed
     */
    public function __call($method, $args)
    {
        if ($this->isGetMethod($method)) {
            $this->applyFilter();
            $this->applyScope();
            $result = call_user_func_array([$this->model, $method], $args);
            $this->result = $result;
            if ($result instanceof Builder || $result instanceof Model) {
                $this->model = $result;
            }
            return $this;
        }

        $result = call_user_func_array([$this->model, $method], $args);
        if ($result instanceof Builder || $result instanceof Model) {
            $this->model = $result;
            return $this;
        }
        return $result;
    }

    /**
     * Retrieve all data of repository.
     *
     * @param array $columns
     *
     * @return mixed
     */
    public function all($columns = ['*'])
    {

        $this->applyFilter();
        $this->applyScope();
        if ($this->model instanceof Builder) {
            $this->result = $this->model->get($columns);
        } else {
            $this->result = $this->model->all($columns);
        }
        $this->model = $this->result;

        return $this;

    }

    /**
     * @param $method
     * @return mixed
     */
    public function isGetMethod($method)
    {
        if (in_array($method, ['get', 'first', 'find', 'simplePaginate', 'paginate'])) {
            return true;
        }

        if (Str::startsWith($method, 'find')) {
            return true;
        }

        if (Str::startsWith($method, 'first')) {
            return true;
        }

        return false;

    }
}