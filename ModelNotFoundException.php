<?php
namespace MJ\WPORM;

/**
 * Thrown by findOrFail() / firstOrFail() (and any other "...OrFail" style
 * lookup) when no matching record exists, mirroring Laravel Eloquent's
 * Illuminate\Database\Eloquent\ModelNotFoundException.
 *
 * Carries the offending model class (and, when available, the primary key
 * value(s) that were searched for) so callers/exception handlers can build
 * a meaningful 404 response without re-parsing the message string.
 */
class ModelNotFoundException extends \RuntimeException {
    /**
     * Class name of the model that could not be found.
     * @var string
     */
    protected $model;

    /**
     * The primary key value(s) that were searched for, if any.
     * @var mixed
     */
    protected $ids;

    /**
     * Set the affected model class and (optionally) the id(s) that were
     * searched for, building an Eloquent-style message in the process.
     *
     * @param string $model The fully-qualified model class name.
     * @param mixed $ids A single id, an array of ids, or null.
     * @return $this
     */
    public function setModel($model, $ids = null) {
        $this->model = $model;
        $this->ids = $ids;

        $message = "No query results for model [{$model}]";

        if (!empty($ids)) {
            $ids = is_array($ids) ? $ids : [$ids];
            $message .= ' ' . implode(', ', $ids);
        }

        $this->message = $message;

        return $this;
    }

    /**
     * Get the class name of the model that could not be found.
     * @return string
     */
    public function getModel() {
        return $this->model;
    }

    /**
     * Get the primary key value(s) that were searched for, if any.
     * @return mixed
     */
    public function getIds() {
        return $this->ids;
    }
}
