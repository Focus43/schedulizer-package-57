<?php namespace Concrete\Package\Schedulizer\Src\Bin {

    use \DateTime;
    use \DateTimeZone;
    use \Concrete\Package\Schedulizer\Src\Event;
    use \Concrete\Package\Schedulizer\Src\EventList;

    /**
     * This class implements JsonSerializable so it can be sent directly
     * to json_encode() and will format OK.
     * @package Concrete\Package\Schedulizer\Src\Api\Utilities
     */
    class EventListSerializeFormatter implements \JsonSerializable {

        protected $formatted = array();
        protected $timezoneUTC;
        protected $eventList;

        /**
         * @return array|mixed
         */
        public function jsonSerialize(){
            return $this->formatted;
        }

        /**
         * Pass in a PREPARED (eg. all settings configured) EventList
         * object, and this will take care of fetching and casting
         * all the results so you can just json_encode($this).
         * @param EventList $eventList
         */
        public function __construct( EventList $eventList ){
            $this->timezoneUTC = new DateTimeZone('UTC');
            $this->eventList   = $eventList;
            $this->queryAndFormat();
        }

        /**
         * In order to have the json_encode method properly cast values,
         * we have to take all the row results and cast them to internal
         * types. This takes care of doing so dynamically based on the
         * column settings in EventList.
         */
        protected function queryAndFormat(){
            $columnSettings  = $this->eventList->getQueryColumnSettings();
            $fetchAttributes = $this->eventList->getAttributesToFetch();
            foreach($this->eventList->get() AS $row){
                $data = new \stdClass();
                foreach($columnSettings AS $columnName => $definition){
                    switch( $definition[1] ){
                        case EventList::COLUMN_CAST_BOOL:
                            $data->{$columnName} = (bool)$row[$columnName];
                            break;
                        case EventList::COLUMN_CAST_INT:
                            $data->{$columnName} = (int)$row[$columnName];
                            break;
                        case EventList::COLUMN_CAST_TIME:
                            if( $definition[2] === EventList::COLUMN_CAST_TIME_UTC ){
                                $data->{$columnName} = (new DateTime($row[$columnName], $this->timezoneUTC))->format('c');
                            }else{ // $definition[2] === EventList::COLUMN_CAST_TIME_LOCAL
                                $data->{$columnName} = (new DateTime($row[$columnName], new DateTimeZone($row['derivedTimezone'])))->format('c');
                            }
                            break;
                        default:
                            $data->{$columnName} = $row[$columnName];
                            break;
                    }
                }

                if( $this->eventList->doIncludeFilePath() ){
                    if( $row['fileID'] ){
                        $fileObj = \Concrete\Core\File\File::getByID($row['fileID']);
                        if( is_object($fileObj) ){
                            $data->filePath = $fileObj->getThumbnailURL('event_thumb');
                        }
                        //$data->filePath = \Concrete\Core\File\File::getByID($row['fileID'])->getRelativePath();
                    }else{
                        $data->filePath = null;
                    }
                }

                if( $this->eventList->doIncludePagePath() ){
                    if( $row['pageID'] ){
                        $data->pagePath = \Concrete\Core\Page\Page::getByID($row['pageID'])->getCollectionPath();
                    }else{
                        $data->pagePath = null;
                    }
                }

                // Temporary, need to make this dynamic!
                if( !empty($fetchAttributes) ){
                    $eventObj = Event::getByID($row['eventID']);
                    if( $eventObj ){
                        foreach($fetchAttributes AS $key){
                            $data->{$key} = $eventObj->getAttribute($key);
                        }
                    }
                }

                array_push($this->formatted, $data);
            }
        }



    }

}