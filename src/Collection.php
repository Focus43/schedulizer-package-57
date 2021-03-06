<?php namespace Concrete\Package\Schedulizer\Src {

    use Package;
    use Loader;
    use \Concrete\Package\Schedulizer\Src\Calendar;
    use \Concrete\Package\Schedulizer\Src\Event AS SchedulizerEvent;
    use \Concrete\Package\Schedulizer\Src\Persistable\Contracts\Persistant;
    use \Concrete\Package\Schedulizer\Src\Persistable\Mixins\Crud;

    /**
     * Class Collection
     * @package Concrete\Package\Schedulizer\Src
     * @definition({"table":"SchedulizerCollection"})
     * @todo: update primary _eventListQuery to include collections
     */
    class Collection extends Persistant {

        use Crud;

        /** @definition({"cast":"datetime", "declarable":false, "autoSet":["onCreate"]}) */
        protected $createdUTC;

        /** @definition({"cast":"datetime", "declarable":false, "autoSet":["onCreate","onUpdate"]}) */
        protected $modifiedUTC;

        /** @definition({"cast":"string","nullable":true}) */
        protected $title;

        /** @definition({"cast":"string","nullable":false}) */
        protected $handle;

        /** @definition({"cast":"int"}) */
        protected $ownerID;

        /** @param $setters */
        public function __construct( $setters = null ){
            $this->mergePropertiesFrom( $setters );
        }

        /** @return string */
        public function __toString(){ return ucwords( $this->title ); }

        /** @return DateTime|null */
        public function getModifiedUTC(){ return $this->modifiedUTC; }

        /** @return DateTime|null */
        public function getCreatedUTC(){ return $this->createdUTC; }

        /** @return string|null */
        public function getTitle(){ return $this->title; }

        /** @return string|null */
        public function getHandle(){ return $this->handle; }

        /** @return int|null */
        public function getOwnerID(){ return $this->ownerID; }

        /**
         * Auto-generates the handle (before being saved) so that whenever a create/update
         * occurs, it will update the handle (if it changed).
         */
        protected function onBeforePersist(){
            $this->handle = Loader::helper('text')->handle($this->title);
        }

        /**
         * This assumes one-to-many calendar associations are passed as
         * an array of calendarIDs in $data->collectionCalendars.
         * @param $data
         * @return static
         */
        public static function create( $data ){
            $colObj = new static();
            $colObj->mergePropertiesFrom($data);
            $colObj->save();

            foreach($data->collectionCalendars AS $calID){
                self::adhocQuery(function( \PDO $connection ) use ($colObj, $calID){
                    $statement = $connection->prepare("INSERT INTO SchedulizerCollectionCalendars (collectionID,calendarID) VALUES (:collectionID,:calendarID)");
                    $statement->bindValue(":collectionID", $colObj->getID());
                    $statement->bindValue(":calendarID", $calID);
                    return $statement;
                });
            }

            return $colObj;
        }

        /**
         * Even though the pseudo-ORM doesn't handle the $data->collectionCalendars[]
         * property automatically, we still expect it to be set (hence why we're overriding
         * the update() method).
         * @param $data
         */
        public function update( $data ){
            $collectionID = $this->id;
            $calendarIDs  = $data->collectionCalendars;

            // Since we're updating, the very first thing we have to do is make sure that for any
            // calendars no longer in the collection, we move any event records associated with them.
            // So, instead of diff'ing which calendars were added or remove, we can do a single query
            // and say remove collection events that are not parts of calendars (1,2,...)
            $this->deleteCollectionEventsNotInCalendars($calendarIDs);

            // First purge all collection -> calendarID associations
            // THIS CAN BE CONFUSING: with foreign keys, you'd think this would delete all
            // CollectionEvent records as well; not the case. CollectionEvents are tied directly
            // to collections - not via many to many association in this table!
            self::adhocQuery(function( \PDO $connection ) use ($collectionID){
                $statement = $connection->prepare("DELETE FROM SchedulizerCollectionCalendars WHERE collectionID = :collectionID");
                $statement->bindValue(':collectionID', $collectionID);
                return $statement;
            });

            // Now recreate them...
            foreach($calendarIDs AS $calID){
                self::adhocQuery(function( \PDO $connection ) use ($collectionID, $calID){
                    $statement = $connection->prepare("INSERT INTO SchedulizerCollectionCalendars (collectionID,calendarID) VALUES (:collectionID,:calendarID)");
                    $statement->bindValue(":collectionID", $collectionID);
                    $statement->bindValue(":calendarID", $calID);
                    return $statement;
                });
            }

            // Now we run normal update on the collection record
            $this->mergePropertiesFrom($data);
            $this->save();
        }


        /**
         * Return the master collection object
         */
        public static function getMasterCollection(){
            $packageObj = Package::getByHandle(self::PACKAGE_HANDLE);
            return self::getByID($packageObj->configGet($packageObj::CONFIG_MASTER_COLLECTION_ID));
        }


        /**
         * @param $calendarObj \Concrete\Package\Schedulizer\Src\Calendar
         */
        public function addOneCalendar( \Concrete\Package\Schedulizer\Src\Calendar $calendarObj ){
            $collectionID = $this->getID();
            $calendarID   = $calendarObj->getID();
            self::adhocQuery(function( \PDO $connection ) use ($collectionID, $calendarID){
                $statement = $connection->prepare("INSERT INTO SchedulizerCollectionCalendars (collectionID,calendarID) VALUES (:collectionID,:calendarID)");
                $statement->bindValue(":collectionID", $collectionID);
                $statement->bindValue(":calendarID", $calendarID);
                return $statement;
            });
        }


        /**
         * When Collection object gets serialized, append an array of calendarIDs.
         * @return object
         */
        public function jsonSerialize(){
            $properties = (object) get_object_vars($this);
            // return an array of calendar IDs
            $properties->collectionCalendars = array_map(function( $calendarObj ){
                return $calendarObj->getID();
            },$this->fetchCollectionCalendars());
            return $properties;
        }


        /**
         * Get the collection object by its handle.
         * @params string $handle
         * @return \Concrete\Package\Schedulizer\Src\Collection
         */
        public static function getByHandle( $handle ){
            return self::fetchOneBy(function( \PDO $connection, $tableName ) use ($handle){
                $statement = $connection->prepare("SELECT * FROM {$tableName} WHERE handle = :handle");
                $statement->bindValue(':handle', $handle);
                return $statement;
            });
        }


        /**
         * Get a list of all collections.
         * @return array
         */
        public static function fetchAll(){
            return (array) self::fetchMultipleBy(function( \PDO $connection, $tableName ){
                return $connection->prepare("SELECT * FROM {$tableName}");
            });
        }


        /**
         * Get a list of all the calendars (as full objects) that belong to a collection.
         * @return array
         */
        public function fetchCollectionCalendars(){
            return Calendar::fetchCalendarsInCollection($this->id);
        }


        /**
         * Get all the version records for a given event.
         * @param $eventID
         * @return mixed
         */
        public static function fetchEventVersionList( $eventID ){
            $query = self::adhocQuery(function( \PDO $connection ) use ($eventID){
                $statement = $connection->prepare("
                    SELECT * FROM SchedulizerEvent _sevent
                    JOIN SchedulizerEventVersion _seversion ON _seversion.eventID = _sevent.id
                    WHERE _sevent.id = :eventID");
                $statement->bindValue(':eventID', $eventID);
                return $statement;
            });
            return $query->fetchAll(\PDO::FETCH_OBJ);
        }


        /**
         * What is the currently approved versionID for a specific collection event? Used
         * when we're inspecting an INDIVIDUAL event, to denote what the approved current
         * version is.
         * @param $collectionID
         * @param $eventID
         * @return mixed
         */
        public static function fetchApprovedEventVersionRecord( $collectionID, $eventID ){
            $query = self::adhocQuery(function( \PDO $connection ) use ($collectionID, $eventID){
                $statement = $connection->prepare("
                    SELECT * FROM SchedulizerCollectionEvents
                    WHERE collectionID = :collectionID AND eventID = :eventID");
                $statement->bindValue(':collectionID', $collectionID);
                $statement->bindValue(':eventID', $eventID);
                return $statement;
            });
            return $query->fetch(\PDO::FETCH_OBJ);
        }


        /**
         * Approve a SINGLE event at a specified version. By doing this, it automatically
         * assumes we're turning autoApproval to off.
         * @param $eventID
         * @param $approvedVersionID
         */
        public function approveEventVersion( $eventID, $approvedVersionID ){
            $collectionID = $this->id;

            self::adhocQuery(function( \PDO $connection ) use ($collectionID, $eventID, $approvedVersionID){
                $statement = $connection->prepare("
                REPLACE INTO SchedulizerCollectionEvents (collectionID,eventID,approvedVersionID,autoApprovable)
                VALUES (:collectionID,:eventID,:approvedVersionID,:autoApprovable)");
                $statement->bindValue(":collectionID", $collectionID);
                $statement->bindValue(":eventID", $eventID);
                $statement->bindValue(":approvedVersionID", $approvedVersionID);
                $statement->bindValue(":autoApprovable", 0);
                return $statement;
            });

            // Now try and bust the page cache!
            $eventObj = SchedulizerEvent::getByID($eventID);
            if( is_object($eventObj) ){
                $eventObj->bustPageCache();
            }
        }


        /**
         * Mark a single event as auto-approvable.
         * @param $eventID
         * @param $isApprovable
         */
        public function markEventAutoApprovable( $eventID, $isApprovable ){
            $collectionID = $this->id;
            $eventObj     = SchedulizerEvent::getByID($eventID);

            self::adhocQuery(function( \PDO $connection ) use ($collectionID, $eventObj, $isApprovable){
                $statement = $connection->prepare("
                    REPLACE INTO SchedulizerCollectionEvents (collectionID,eventID,approvedVersionID,autoApprovable)
                    VALUES (:collectionID,:eventID,:approvedVersionID,:autoApprovable)
                ");
                $statement->bindValue(":collectionID", $collectionID);
                $statement->bindValue(":eventID", $eventObj->getID());
                $statement->bindValue(":approvedVersionID", $eventObj->getVersionID());
                $statement->bindValue(":autoApprovable", (int)$isApprovable);
                return $statement;
            });

            // Now try and bust the page cache!
            $eventObj = SchedulizerEvent::getByID($eventID);
            if( is_object($eventObj) ){
                $eventObj->bustPageCache();
            }
        }


        /**
         * Given a list of eventIDs, this will mark the latest versions as approved for
         * all events in the given collection.
         * @todo: sql-injection vulnerability where we're just doing join()
         * directly on user input; validate that shit first.
         * @param array $eventIDs
         */
        public function approveEventsAtLatestVersion( array $eventIDs = array() ){
            $collectionID = $this->id;
            $eventIDs     = join(',', $eventIDs);
            self::adhocQuery(function( \PDO $connection ) use ($collectionID, $eventIDs){
                $statement = $connection->prepare("
                  REPLACE INTO SchedulizerCollectionEvents (collectionID, eventID, approvedVersionID)
                  SELECT :collectionID, _events.id, _versionInfo.versionID
                  FROM SchedulizerEvent _events
                  LEFT JOIN (
                      SELECT _eventVersions.*
                      FROM SchedulizerEventVersion _eventVersions
                      INNER JOIN ( SELECT eventID, MAX(versionID) AS maxVersionID FROM SchedulizerEventVersion GROUP BY eventID ) _eventVersions2
                      ON _eventVersions.eventID = _eventVersions2.eventID
                      AND _eventVersions.versionID = _eventVersions2.maxVersionID
                  ) AS _versionInfo ON _events.id = _versionInfo.eventID
                  WHERE _events.id IN ($eventIDs)
                ");
                $statement->bindValue(":collectionID", $collectionID);
                return $statement;
            });

            // @todo: limit scope fo this cache bust - this is clearing EVERYTHING now
            \Core::make('app')->clearCaches();
        }


        /**
         * "Unapprove" means "delete" all the event approvals from the collection's
         * event records.
         * @param array $eventIDs
         * @return void
         */
        public function unapproveCollectionEvents( array $eventIDs = array() ){
            $collectionID = $this->id;
            $eventIDs = join(',', $eventIDs);
            self::adhocQuery(function( \PDO $connection ) use ($collectionID, $eventIDs){
                $statement = $connection->prepare("
                  DELETE FROM SchedulizerCollectionEvents
                  WHERE collectionID = :collectionID
                  AND eventID IN ($eventIDs)
                ");
                $statement->bindValue(":collectionID", $collectionID);
                return $statement;
            });
        }


        /**
         * When we're updating a collection (and thus possibly changing calendars which
         * are members of the collection), we need to make sure to remove any events
         * that have been approved in the collection that belong to calendars which are
         * no longer members.
         * @param array $calendarIDs
         */
        public function deleteCollectionEventsNotInCalendars( array $calendarIDs = array() ){
            $collectionID = $this->id;
            $calendarIDs  = join(',', $calendarIDs);
            self::adhocQuery(function( \PDO $connection ) use ($collectionID, $calendarIDs){
                $statement = $connection->prepare("
                    DELETE _collEvent FROM SchedulizerCollectionEvents _collEvent
                    JOIN SchedulizerEvent _schedEvent ON _collEvent.eventID = _schedEvent.id
                    JOIN SchedulizerCollectionCalendars _collCalendar ON _collCalendar.calendarID = _schedEvent.calendarID
                    AND _collCalendar.collectionID = _collEvent.collectionID
                    WHERE _collEvent.collectionID = :collectionID
                    AND _schedEvent.calendarID NOT IN ($calendarIDs)
                ");
                $statement->bindValue(":collectionID", $collectionID);
                return $statement;
            });
        }

    }

}