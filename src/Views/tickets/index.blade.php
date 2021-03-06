@extends($master)

@section('page')
    {{ trans('panichd::lang.index-title') }}
@stop

@include('panichd::shared.common')

@if (PanicHD\PanicHD\Models\Ticket::count() == 0)
	@section('content')
		<div class="panel panel-default">
			<div class="panel-body">
				{{ trans('panichd::lang.no-tickets-yet') }}
			</div>
		</div>
	@stop
@else
	@section('content')
		@include('panichd::tickets.partials.filter_panel')
		<div class="panel panel-default">
			<div class="panel-body">
				<div id="message"></div>
				@include('panichd::tickets.partials.datatable')
			</div>
		</div>
		@include('panichd::tickets.partials.modal_agent')
		@include('panichd::tickets.partials.priority_popover_form')
	@stop

	@section('footer')
		<script src="//cdn.datatables.net/v/bs/dt-{{ PanicHD\PanicHD\Helpers\Cdn::DataTables }}/r-{{ PanicHD\PanicHD\Helpers\Cdn::DataTablesResponsive }}/datatables.min.js"></script>
		<script>
		$(function(){	
			// Ticket list load
			$('.table').DataTable({
				processing: false,
				serverSide: true,
				responsive: true,
				pageLength: {{ $setting->grab('paginate_items') }},
				lengthMenu: {{ json_encode($setting->grab('length_menu')) }},
				ajax: '{!! route($setting->grab('main_route').'.data', $ticketList) !!}',
				language: {
					decimal:        "{{ trans('panichd::lang.table-decimal') }}",
					emptyTable:     "{{ trans('panichd::lang.table-empty') }}",
					info:           "{{ trans('panichd::lang.table-info') }}",
					infoEmpty:      "{{ trans('panichd::lang.table-info-empty') }}",
					infoFiltered:   "{{ trans('panichd::lang.table-info-filtered') }}",
					infoPostFix:    "{{ trans('panichd::lang.table-info-postfix') }}",
					thousands:      "{{ trans('panichd::lang.table-thousands') }}",
					lengthMenu:     "{{ trans('panichd::lang.table-length-menu') }}",
					loadingRecords: "{{ trans('panichd::lang.table-loading-results') }}",
					processing:     "{{ trans('panichd::lang.table-processing') }}",
					search:         "{{ trans('panichd::lang.table-search') }}",
					zeroRecords:    "{{ trans('panichd::lang.table-zero-records') }}",
					paginate: {
						first:      "{{ trans('panichd::lang.table-paginate-first') }}",
						last:       "{{ trans('panichd::lang.table-paginate-last') }}",
						next:       "{{ trans('panichd::lang.table-paginate-next') }}",
						previous:   "{{ trans('panichd::lang.table-paginate-prev') }}"
					},
					aria: {
						sortAscending:  "{{ trans('panichd::lang.table-aria-sort-asc') }}",
						sortDescending: "{{ trans('panichd::lang.table-aria-sort-desc') }}"
					},
				},
				<?php
					$agent_column = session('panichd_filter_agent')=="" && $u->currentLevel() > 1 ? true : false;
					$priority_column_addition = $calendar_column_addition = 0;
					$user_updated_at_addition = 0;
					1; // Counts priority_magnitude column
					if (session('panichd_filter_owner')=="") $calendar_column_addition++;
					if($setting::grab('departments_feature')) $calendar_column_addition++;
				?>				
				columns: [
					{ data: 'id', name: 'panichd_tickets.id' }, // order == 0
					{ data: 'subject', name: 'subject' },
					@if ($setting->grab('subject_content_column') == 'no')
						<?php $priority_column_addition++;
							$user_updated_at_addition++;
						?>
						{ data: 'content', name: 'content' },
					@endif
					{ data: 'intervention', name: 'intervention' },
					{ data: 'status', name: 'panichd_statuses.name' },
					@if ($agent_column)
						<?php $priority_column_addition++; ?>
						{ data: 'agent', name: 'agent.name' },
					@endif				
					@if( $u->currentLevel() > 1 )
						{ data: 'priority', name: 'panichd_priorities.name',
							"orderData": [<?php echo 5+$priority_column_addition; ?>, <?php echo 6+$priority_column_addition+$calendar_column_addition; ?>],
							"orderSequence": ['desc', 'asc']},
						{ data: 'priority_magnitude', name: 'panichd_priorities.magnitude', visible: false },
						@if (session('panichd_filter_owner')=="")
							{ data: 'owner_name', name: 'users.name' },
							@if ($setting::grab('departments_feature'))
								{ data: 'dept_info', name: 'dept_full', searchable: false },
							@endif
						@endif
						@if ($ticketList == 'complete')
							{ data: 'complete_date', name: 'completed_at', searchable: false, "orderSequence": [ "desc", "asc"] },
						@else
							{ data: 'calendar', name: 'calendar_order', searchable: false, "orderSequence": [ "desc", "asc"] },
						@endif
					@endif
					{ data: 'updated_at', name: 'panichd_tickets.updated_at' },
					@if( $u->currentLevel() > 1 )
						@if (session('panichd_filter_category')=="")
							{ data: 'category', name: 'panichd_categories.name' },
						@endif
						{ data: 'tags', name: 'panichd_tags.name' }
					@endif				
				],
				@if($ticketList!='newest')
					@if( $u->currentLevel() > 1)
						@if ($ticketList=='active')
							order: [
								[<?php echo 5+$priority_column_addition; ?>, 'desc'],
								[<?php echo 6+$priority_column_addition+$calendar_column_addition; ?>, 'desc'],
							]
						@else
							order: [<?php echo 7+$priority_column_addition+$calendar_column_addition; ?>, 'desc']
						@endif
					@else
						order: [<?php echo 4+$user_updated_at_addition; ?>, 'desc']
					@endif
				@endif
				
			});		
			
			// Ticket List: Change ticket agent
			$('#tickets-table').on('draw.dt', function(e){
				
				// Agent change: Modal for > 4 agents
				$('.jquery_agent_change_modal').click(function(e){
					e.preventDefault();				
					
					// Row hover
					$(this).closest('tr').addClass('hover');				
					
					// Form fields
					$('#modalAgentChange #agent_ticket_id_field').val($(this).attr('data-ticket-id'));
					
					// Modal itself
					$('#modalAgentChange').modal('show');
					$('#modalAgentChange .categories_agent_change').hide();
					$('#modalAgentChange #category_'+$(this).attr('data-category-id')+'_agents').show()
						.find(":radio[value="+$(this).attr('data-agent-id')+"]").prop('checked',true);
				});
				
				$('#modalAgentChange').on('hidden.bs.modal', function () {
					$(document).find('tr').removeClass('hover');
				});
				
				// Agent / Priority change: Popover menu
				$(".jquery_popover")
					.tooltip({
						placement: 'top',
						trigger: "hover"
					})
					.popover({ html: true})
				.click(function(e){
					e.preventDefault();
				});
				
				// Agent change: Popover menu submit
				$(document).on('click','.submit_agent_popover',function(e){
					e.preventDefault();
									
					// Form fields
					$('#modalAgentChange #agent_ticket_id_field').val($(this).attr('data-ticket-id'));
					$('#modalAgentChange').find(":radio[value="+$(this).parent('div').find('input[name='+$(this).attr('data-ticket-id')+'_agent]:checked').val()+"]").prop('checked',true);
				
					// Form submit
					$('#modalAgentChange').find('form').submit();
					
				});
				
				// Agent change: Popover menu submit
				$(document).on('click','.submit_priority_popover',function(e){
					e.preventDefault();
									
					// Form fields
					$('#PriorityPopoverForm #priority_ticket_id_field').val($(this).attr('data-ticket-id'));
					var priority_val = $(this).parent('div').find('input[name='+$(this).attr('data-ticket-id')+'_priority]:checked').val();
					$('#PriorityPopoverForm #priority_id_field').val(priority_val);
				
					// Form submit
					$('#PriorityPopoverForm').find('form').submit();
					
				});

				// Agent change: Tooltip for 1 agents
				$(".tooltip-info").tooltip();
			});

			@yield('footer_jquery')
		});
		</script>
	@append
@endif