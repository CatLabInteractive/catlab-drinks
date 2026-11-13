<?php

namespace App\Services;

use CatLab\Requirements\Collections\MessageCollection;
use CatLab\Requirements\Exceptions\ResourceValidationException;
use CatLab\Requirements\Models\Message;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Stores the position of the rows of an event relation (products, headings).
 */
class SequenceWriter
{
	/**
	 * Listed rows get position 1..k in list order, the other rows of the parent
	 * (soft deleted ones included) follow in their current order.
	 *
	 * @param HasMany $relation
	 * @param mixed $items The decoded "items" of the request body
	 * @throws ResourceValidationException
	 */
	public function write(HasMany $relation, mixed $items): void
	{
		if (!is_array($items) || count($items) === 0) {
			$this->fail('Items must be a non-empty list.');
		}

		$ids = [];
		foreach ($items as $item) {
			$id = is_array($item) ? ($item['id'] ?? null) : null;
			if (!is_int($id)) {
				$this->fail('Every item must have an integer id.');
			}
			$ids[] = $id;
		}

		if (count(array_unique($ids)) !== count($ids)) {
			$this->fail('Every row can be listed only once.');
		}

		$related = $relation->getRelated();
		$foreignKey = $relation->getForeignKeyName();
		$parentKey = $relation->getParentKey();

		$valid = $related->newQuery()->where($foreignKey, $parentKey)->whereIn('id', $ids)->count();
		if ($valid !== count($ids)) {
			$this->fail('Every id must belong to this event and must not be deleted.');
		}

		\DB::transaction(function () use ($related, $foreignKey, $parentKey, $ids) {
			$position = 1;
			foreach ($ids as $id) {
				$related->newQuery()->withTrashed()->where('id', $id)->update([ 'position' => $position++ ]);
			}

			$others = $related->newQuery()->withTrashed()
				->where($foreignKey, $parentKey)
				->whereNotIn('id', $ids)
				->orderBy('position')
				->orderBy('id')
				->pluck('id');

			foreach ($others as $id) {
				$related->newQuery()->withTrashed()->where('id', $id)->update([ 'position' => $position++ ]);
			}
		});
	}

	/**
	 * @param string $message
	 * @throws ResourceValidationException
	 */
	private function fail(string $message): void
	{
		$messages = new MessageCollection();
		$messages->add(new Message($message));

		throw ResourceValidationException::make($messages);
	}
}
