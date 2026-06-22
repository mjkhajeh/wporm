<?php
namespace MJ\WPORM\Events;

/** Fired before a new model is inserted into the database. */
class Creating extends ModelEvent {}

/** Fired after a new model has been inserted into the database. */
class Created extends ModelEvent {}

/** Fired before an existing model is updated in the database. */
class Updating extends ModelEvent {}

/** Fired after an existing model has been updated in the database. */
class Updated extends ModelEvent {}

/** Fired before a model is saved (insert or update). */
class Saving extends ModelEvent {}

/** Fired after a model has been saved (insert or update). */
class Saved extends ModelEvent {}

/** Fired before a model is deleted from the database (hard delete). */
class Deleting extends ModelEvent {}

/** Fired after a model has been deleted from the database (hard delete). */
class Deleted extends ModelEvent {}

/** Fired before a model is soft-deleted. */
class SoftDeleting extends ModelEvent {}

/** Fired after a model has been soft-deleted. */
class SoftDeleted extends ModelEvent {}

/** Fired before a soft-deleted model is restored. */
class Restoring extends ModelEvent {}

/** Fired after a soft-deleted model has been restored. */
class Restored extends ModelEvent {}

/** Fired after a model is retrieved from the database. */
class Retrieved extends ModelEvent {}
