<?php
namespace AggregatePersistence;

trait ChangeNotifier
{
    private $nextChangeObserverId = 1;
    private $changeObservers = [];

    public function onChange(callable $observer)
    {
        $observerId = $this->nextChangeObserverId++;
        $this->changeObservers[$observerId] = $observer;
        $canceller = function () use ($observerId) {
            if (isset($this->changeObservers[$observerId])) {
                unset($this->changeObservers[$observerId]);
            }
        };

        return $canceller;
    }

    public function notifyChangeObservers()
    {
        foreach ($this->changeObservers as $observer) {
            call_user_func($observer);
        }
    }

    public function propagateChangesFrom($subject)
    {
        $subject->onChange([$this, 'notifyChangeObservers']);
    }
}
