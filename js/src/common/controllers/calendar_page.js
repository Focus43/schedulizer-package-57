angular.module('schedulizer.app').

    controller('CtrlCalendarPage', ['$rootScope', '$scope', '$http', '$cacheFactory', 'API', 'Alerter',
        function( $rootScope, $scope, $http, $cacheFactory, API, Alerter ){

            $scope.updateInProgress     = false;
            $scope.searchOpen           = false;
            $scope.eventTagList         = [];
            $scope.eventCategoryList    = [];
            $scope.searchFiltersSet     = false;
            $scope.searchFields         = {
                keywords: null,
                tags: [],
                categories: []
            };

            $scope.toggleSearch = function(){
                $scope.searchOpen = !$scope.searchOpen;
            };

            API.eventTags.query().$promise.then(function( results ){
                $scope.eventTagList = results;
            });

            API.eventCategories.query().$promise.then(function( results ){
                $scope.eventCategoryList = results;
            });

            // $scope.calendarID is ng-init'd from the view!
            var _cache = $cacheFactory('calendarData');

            // Tell the API what fields we want back
            var _fields = [
                'eventID', 'eventTimeID', 'calendarID', 'title', 'isActive',
                'eventColor', 'isAllDay', 'isSynthetic', 'computedStartUTC',
                'computedStartLocal'
            ];

            /**
             * Turn the search button green if any search fields are filled in to indicate
             * to the user that search filters are being applied.
             */
            $scope.$watch('searchFields', function(val){
                var filtersSet = false;
                if( val.keywords ){filtersSet = true;}
                if( val.tags.length !== 0 ){filtersSet = true;}
                if( val.categories.length !== 0 ){filtersSet = true;}
                $scope.searchFiltersSet = filtersSet;
            }, true);

            /**
             * We need to pre-process the $scope.searchFields and format them for
             * querying; this does so.
             * @returns {{keywords: null, tags: *}}
             */
            function parameterizedSearchFields(){
                return {
                    includeinactives: true,
                    keywords: $scope.searchFields.keywords,
                    tags: $scope.searchFields.tags.map(function( tag ){
                        return tag.id;
                    }).join(','),
                    categories: $scope.searchFields.categories.map(function( cat ){
                        return cat.id;
                    }).join(',')
                };
            }

            /**
             * Receive a month map object from calendry and setup the request as
             * you see fit.
             * @param monthMapObj
             * @returns {HttpPromise}
             * @private
             */
            function _fetch( monthMapObj ){
                return $http.get(API._routes.generate('api.eventList', [$scope.calendarID]), {
                    cache: _cache,
                    params: angular.extend({
                        start: monthMapObj.calendarStart.format('YYYY-MM-DD'),
                        end: monthMapObj.calendarEnd.format('YYYY-MM-DD'),
                        fields: _fields.join(',')
                    }, parameterizedSearchFields())
                });
            }

            /**
             * Trigger refreshing the calendar.
             * @private
             */
            function _updateCalendar( uncache ){
                $scope.updateInProgress = true;
                if( uncache === true ){
                    _cache.removeAll();
                }
                _fetch($scope.instance.monthMap).success(function( resp ){
                    $scope.instance.events = resp;
                    $scope.updateInProgress = false;
                }).error(function( data, status, headers, config ){
                    $scope.updateInProgress = false;
                    console.warn(status, 'Failed fetching calendar data.');
                });
                $scope.searchOpen = false;
            }

            /**
             * Clear the search fields and update calendar.
             */
            $scope.clearSearchFields = function(){
                $scope.searchFields = {
                    keywords: null,
                    tags: [],
                    categories: []
                };
                _updateCalendar();
            };

            /**
             * Method to trigger calendar refresh callable from the scope.
             * @type {_updateCalendar}
             */
            $scope.sendSearch = function(){
                _updateCalendar();
            };

            /**
             * Handlers for calendry stuff.
             * @type {{onMonthChange: Function, onDropEnd: Function}}
             */
            $scope.instance = {
                parseDateField: 'computedStartLocal',
                onMonthChange: function( monthMap ){
                    _updateCalendar();
                },
                onDropEnd: function( landingMoment, eventObj ){
                    console.log(landingMoment, eventObj);
                }
            };

            /**
             * calendar.refresh IS NOT issued by the calendry directive; it comes
             * from other things in the app.
             */
            $rootScope.$on('calendar.refresh', function(){
                _updateCalendar(true);
            });

            // Launch C5's default modal stuff
            $scope.permissionModal = function( _href ){
                jQuery.fn.dialog.open({
                    title:  'Calendar Permissions',
                    href:   _href + '&bustCache=' + Math.random().toString(36).substring(20) + Math.round(Math.random()*100000000000).toString(),
                    modal:  false,
                    width:  500,
                    height: 380
                });
            };

            // If the user doesn't have permission to edit calendar events
            $scope.warnNoPermission = function(){
                Alerter.add({duration:1500, msg:'You do not have permission to edit.', danger:true});
            };

        }
    ]);
