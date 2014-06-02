<?php namespace Jenssegers\Mongodb\Eloquent;

trait SoftDeletingTrait {

	use \Illuminate\Database\Eloquent\SoftDeletingTrait;

	/**
	 * Get the fully qualified "deleted at" column.
	 *
	 * @return string
	 */
	public function getQualifiedDeletedAtColumn()
	{
		return $this->getDeletedAtColumn();
	}

}
