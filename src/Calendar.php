<?php namespace Concrete\Package\Schedulizer\Src {

    use DateTimeZone;
    use \Concrete\Package\Schedulizer\Src\Persistable\Contracts\Persistant;
    use \Concrete\Package\Schedulizer\Src\Persistable\Mixins\Crud;
    use Permissions;
    use Router;
    use Loader;
    use \Concrete\Core\Permission\Access\Access AS PermissionAccess;
    //use \Concrete\Core\Permission\Key\Key AS PermissionKey;
    use \Concrete\Core\User\Group\Group;
    use \Concrete\Core\Permission\Access\Entity\GroupEntity as GroupPermissionAccessEntity;
    use \Concrete\Package\Schedulizer\Src\Permission\Access\Entity\CalendarOwnerEntity AS CalendarOwnerAccessEntity;
    use \Concrete\Package\Schedulizer\Src\Permission\Key\SchedulizerKey AS SchedulizerPermKey;
    use \Concrete\Package\Schedulizer\Src\Permission\Key\SchedulizerCalendarKey AS SchedulizerCalendarPermKey;
    use \Concrete\Core\Permission\Category AS PermissionKeyCategory;
    use User;

    /**
     * Class Calendar
     * @package Concrete\Package\Schedulizer\Src
     * @definition({"table":"SchedulizerCalendar"})
     */
    class Calendar extends Persistant implements \Concrete\Core\Permission\ObjectInterface {

        // req'd by PermissionableEntityMixin
        const PERMISSION_KEY_CATEGORY = 'schedulizer_calendar';

        use Crud;

        /** @definition({"cast":"datetime", "declarable":false, "autoSet":["onCreate"]}) */
        protected $createdUTC;

        /** @definition({"cast":"datetime", "declarable":false, "autoSet":["onCreate","onUpdate"]}) */
        protected $modifiedUTC;

        /** @definition({"cast":"string","nullable":true}) */
        protected $title;

        /** @definition({"cast":"int"}) */
        protected $ownerID;

        /** @definition({"cast":"string"}) */
        protected $defaultTimezone = 'UTC';

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

        /** @return int|null */
        public function getOwnerID(){ return $this->ownerID; }

        /** @return string */
        public function getDefaultTimezone(){ return $this->defaultTimezone; }

        /**
         * @return DateTimeZone
         */
        public function getCalendarTimezoneObj(){
            if( $this->_calendarTimezoneObj === null ){
                $this->_calendarTimezoneObj = new DateTimeZone( $this->getDefaultTimezone() );
            }
            return $this->_calendarTimezoneObj;
        }

        /**
         * Return properties for JSON serialization
         * @return array|mixed
         */
        public function jsonSerialize(){
            if( ! $this->isPersisted() ){
                $properties = (object) get_object_vars($this);
                unset($properties->id);
                unset($properties->createdUTC);
                unset($properties->modifiedUTC);
                return $properties;
            }
            $properties                 = (object) get_object_vars($this);
            $properties->createdUTC     = $properties->createdUTC->format('c');
            $properties->modifiedUTC    = $properties->modifiedUTC->format('c');
            return $properties;
        }

        public function onAfterPersist(){
            // Add events
            $pkAddEvents = SchedulizerCalendarPermKey::getByHandle('add_events');
            $pkAddEvents->setPermissionObject($this);
            $pa = $pkAddEvents->getPermissionAccessObject();
            if( !is_object($pa) ){
                $pa = PermissionAccess::create($pkAddEvents);
            }
            $peCalendarOwner = CalendarOwnerAccessEntity::getOrCreate();
            $pa->addListItem($peCalendarOwner);
            $peAdministrators = GroupPermissionAccessEntity::getOrCreate(Group::getByID(ADMIN_GROUP_ID));
            $pa->addListItem($peAdministrators);
            $pkAddEvents->getPermissionAssignmentObject()->assignPermissionAccess($pa);

            // Delete events
            $pkDeleteEvents = SchedulizerCalendarPermKey::getByHandle('delete_events');
            $pkDeleteEvents->setPermissionObject($this);
            $pa = $pkDeleteEvents->getPermissionAccessObject();
            if( !is_object($pa) ){
                $pa = PermissionAccess::create($pkDeleteEvents);
            }
            $peCalendarOwner = CalendarOwnerAccessEntity::getOrCreate();
            $pa->addListItem($peCalendarOwner);
            $peAdministrators = GroupPermissionAccessEntity::getOrCreate(Group::getByID(ADMIN_GROUP_ID));
            $pa->addListItem($peAdministrators);
            $pkDeleteEvents->getPermissionAssignmentObject()->assignPermissionAccess($pa);
        }

        /****************************************************************
         * Fetch Methods
         ***************************************************************/

        public static function fetchAll(){
            return (array) self::fetchMultipleBy(function( \PDO $connection, $tableName ){
                return $connection->prepare("SELECT * FROM {$tableName}");
            });
        }


        /****************************************************************
         * Permissioning stuff
         ***************************************************************/

        /**
         * Ideally you could create a custom "...Category" class, but the current
         * structure doesn't permit overriding.
         */
        public function getPermissionCategoryToolsUrlShim( $task = false ){
            if( ! $task ){
                $task = 'save_permission';
            }
            $query = http_build_query(array(
                'task'       => $task,
                'calendarID' => $this->id
            )) . sprintf("&%s", Loader::helper('validation/token')->getParameter($task));

            return Router::route(array(sprintf('permission/category/schedulizer_calendar?%s',$query), 'schedulizer'));
        }

        /**
         * @return \Concrete\Core\Permission\Category
         */
        public function getPermissionKeyCategory(){
            if( $this->_permissionKeyCategory === null ){
                $this->_permissionKeyCategory = PermissionKeyCategory::getByHandle(self::PERMISSION_KEY_CATEGORY);
            }
            return $this->_permissionKeyCategory;
        }

        /**
         * @return \Concrete\Core\Permission\Checker
         */
        public function getPermissions(){
            if( $this->_permissions === null ){
                $this->_permissions = new Permissions($this);
            }
            return $this->_permissions;
        }

        /**
         * @return string
         */
        public function getPermissionResponseClassName(){
            return '\\Concrete\\Package\\Schedulizer\\Src\\Permission\\Response\\SchedulizerCalendarResponse';
        }

        /**
         * @return string
         */
        public function getPermissionAssignmentClassName(){
            return '\\Concrete\\Package\\Schedulizer\\Src\\Permission\\Assignment\\SchedulizerCalendarAssignment';
        }

        /**
         * @return string
         */
        public function getPermissionObjectKeyCategoryHandle(){
            return self::PERMISSION_KEY_CATEGORY;
        }

        /**
         * @return int|null
         */
        public function getPermissionObjectIdentifier(){
            return $this->getID();
        }
    }

}