<?php $permissions = new Permissions(); ?>
<form name="frmEventData" class="event container-fluid" ng-controller="CtrlEventForm">
    <?php Loader::packageElement('templates/loading', 'schedulizer'); ?>

    <div class="row" ng-show="warnAliased">
        <div class="col-sm-12 text-center">
            <blockquote class="text-left"><strong>Heads Up!</strong> The event you clicked is one in a repeating series. To update it, you have to update the original event, which will cascade to <i>all</i> events in the series.<br/><br/><strong>Or,</strong> you can hide just this event from within the series.</blockquote>
            <button type="button" class="btn btn-info" ng-click="warnAliased = false">Edit Original Event</button>
            <button type="button" class="btn btn-warning" ng-click="nullifyInSeries()">Hide Just This Event From The Series</button>
        </div>
    </div>

    <div ng-show="(_ready && !warnAliased)">
        <ul class="nav nav-tabs">
            <li ng-click="setMasterTabActive(1)" ng-class="{'active':activeMasterTab[1]}"><a>Basic Info</a></li>
            <li ng-click="setMasterTabActive(2)" ng-class="{'active':activeMasterTab[2]}"><a>Custom Attributes</a></li>

            <?php if( $permissions->canManageCollections() ): ?>
                <li class="pull-right">
                    <button type="button" class="btn btn-primary save-entity" ng-click="submitHandler()" ng-disabled="(frmEventData.$pristine || frmEventData.$invalid)">
                        <span ng-hide="_requesting">Save</span>
                        <img ng-show="_requesting" src="<?php echo SCHEDULIZER_IMAGE_PATH; ?>spinner.svg" />
                    </button>
                </li>
            <?php else: ?>
                <li class="pull-right" ng-hide="(_requestingApproval || _requesting)">
                    <button type="button" class="btn btn-success save-entity" ng-click="submitForApprovalHandler()" ng-disabled="(frmEventData.$pristine || frmEventData.$invalid)">
                        <span>Submit For Approval</span>
                    </button>
                </li>
                <li class="pull-right">
                    <button type="button" class="btn btn-primary save-entity" ng-click="submitHandler()" ng-disabled="(frmEventData.$pristine || frmEventData.$invalid)">
                        <span ng-hide="_requesting">Save Version</span>
                        <img ng-show="_requesting" src="<?php echo SCHEDULIZER_IMAGE_PATH; ?>spinner.svg" />
                    </button>
                </li>
            <?php endif; ?>

            <li class="pull-right delete-entity" ng-show="entity.id">
                <button type="button" class="btn btn-warning" ng-click="confirmDelete = !confirmDelete" ng-hide="confirmDelete">
                    Delete Event
                </button>
                <div ng-show="confirmDelete">
                    <button type="button" class="btn btn-danger" ng-click="deleteEvent()">
                        <strong>Delete It</strong>
                    </button>
                    <button type="button" class="btn btn-info" ng-click="confirmDelete = !confirmDelete">
                        Nevermind!
                    </button>
                </div>
            </li>
        </ul>

        <div class="tab-content">
            <div class="tab-pane" ng-class="{'active':activeMasterTab[1]}">
                <!-- title -->
                <div class="row">
                    <div class="col-sm-12">
                        <div class="form-group title-group">
                            <label for="" class="sr-only">Title</label>
                            <input required name="title" type="text" class="form-control input-title" placeholder="Title (Required)" ng-model="entity.title" />
                            <span select-wrap ng-class="{'active-true':entity.isActive,'active-false':!entity.isActive}" bs-tooltip="'Important: Changing this triggers an immediate update, but only on the event status. No other changes are saved!'" data-template="/tpl-tooltip" data-placement="left" bs-enabled="(+(entity.id) >= 1)">
                                <select class="form-control" ng-options="opt.value as opt.label for opt in isActiveOptions" ng-model="entity.isActive"></select>
                            </span>
                        </div>
                    </div>
                </div>

                <!-- timing tabs -->
                <div class="row">
                    <div class="col-sm-12">
                        <ul class="nav nav-tabs">
                            <li ng-repeat="timing in timingTabs" ng-click="setTimingTabActive($index)" ng-class="{active:timingTabs[$index].active}">
                                <a>{{timing.label}}</a>
                            </li>
                            <li ng-click="addTimeEntity()" class="add-time-entity"><a><i class="icon-plus"></i></a></li>
                        </ul>
                    </div>
                </div>

                <!-- time entities (tab contents) -->
                <div class="tab-content">
                    <div class="tab-pane" ng-repeat="timeEntity in entity._timeEntities" ng-class="{active:timingTabs[$index].active}">
                        <button type="button" class="btn btn-danger btn-xs remove-time-entity" ng-click="removeTimeEntity($index)" ng-show="($index !== 0)">
                            Remove
                        </button>
                        <div event-time-form="timeEntity"></div>
                    </div>
                </div>

                <!-- timezone -->
                <div class="row">
                    <div class="col-sm-12">
                        <div class="form-group white">
                            <span select-wrap class="block"><select class="form-control" ng-options="opt.value as opt.label for opt in useCalendarTimezoneOptions" ng-model="entity.useCalendarTimezone"></select></span>
                        </div>
                    </div>
                </div>
                <div class="row" ng-hide="entity.useCalendarTimezone">
                    <div class="col-sm-12">
                        <div class="form-group white">
                            <span select-wrap class="block"><select class="form-control" ng-options="opt for opt in timezoneOptions" ng-model="entity.timezoneName"></select></span>
                        </div>
                    </div>
                </div>


                <!-- description -->
                <div class="row">
                    <div class="col-sm-12">
                        <div class="form-group">
                            <textarea redactorized ng-model="entity.description"></textarea>
                        </div>
                    </div>
                </div>

                <!-- tags -->
                <div class="row">
                    <div class="col-sm-12">
                        <div class="form-group ui-select-widget">
                            <?php if( $permissions->canCreateTag() ): ?>
                                <ui-select multiple tagging="tagTransform" ng-model="entity._tags" theme="bootstrap" title="Tags">
                            <?php else: ?>
                                <ui-select multiple ng-model="entity._tags" theme="bootstrap" title="Tags">
                            <?php endif; ?>
                                <ui-select-match placeholder="Tags">{{ $item.displayText }}</ui-select-match>
                                <ui-select-choices repeat="tag in eventTagList | propsFilter: {displayText: $select.search}">
                                    <div ng-bind-html="tag.displayText | highlight: $select.search"></div>
                                </ui-select-choices>
                            </ui-select>
                        </div>
                    </div>
                </div>

                <!-- categories -->
                <div class="row">
                    <div class="col-sm-12">
                        <div class="form-group ui-select-widget">
                            <ui-select multiple ng-model="entity._categories" theme="bootstrap" title="Categories">
                                <ui-select-match placeholder="Categories">{{ $item.displayText }}</ui-select-match>
                                <ui-select-choices repeat="category in eventCategoryList | propsFilter: {displayText: $select.search}">
                                    <div ng-bind-html="category.displayText | highlight: $select.search"></div>
                                </ui-select-choices>
                            </ui-select>
                        </div>
                    </div>
                </div>

                <!-- file picker -->
                <div class="row">
                    <div class="col-sm-12">
                        <div class="form-group white">
                            <div class="ccm-file-selector" data-file-selector="fileID"></div>
                        </div>
                    </div>
                </div>

                <!-- event colors -->
                <div class="row">
                    <div class="col-sm-12">
                        <div class="form-group text-center">
                            <label ng-repeat="opt in eventColorOptions" class="color-thumb" ng-style="{background:opt.value}" ng-class="{active:(opt.value == entity.eventColor)}">
                                <input type="radio" ng-model="entity.eventColor" ng-value="opt.value" />
                            </label>
                        </div>
                    </div>
                </div>

            </div>

            <div class="tab-pane" ng-class="{'active':activeMasterTab[2]}">
                <div custom-attributes ng-include="attributeForm" data-onload="decorateAttributes()">
                    <!-- loaded via include -->
                </div>
            </div>
        </div>
    </div>
</form>