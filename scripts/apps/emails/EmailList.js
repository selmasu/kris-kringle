var EmailList = {}

EmailList.dataSource = function()
{
	var Email = kendo.data.Model.define(
	{
		id: 'emailId'
	});
	
	var emailDataSource = new kendo.data.DataSource(
	{
		serverPaging: true,
		serverSorting: true,
		pageSize: 50,
		transport:
		{
			read:
			{
				url: "/json/emails/emails/getEmails",
				dataType: 'json',
				data:
				{
					filters: EmailList.getFilters()
				}
			}
		},
		schema:
		{
			model: Email,
			data: 'data',
			total: 'total'
		},
		sort: { field: 'name', dir: 'asc' }
	});
	
	return emailDataSource;
}

EmailList.getFilters = function()
{
	var filters = 
	{
		search : function()
		{
			return $( '#header-search' ).val();
		}
	}

	return filters;
}

EmailList.setGrid = function()
{
	$( '#emailGrid' ).kendoGrid(
	{
		dataSource: EmailList.dataSource(),
        height: $( window ).height() - 240,
		scrollable:
		{
			virtual: true
		},
		sortable: true,
		selectable: 'multiple',
		columns: [{ field: 'name', title: 'Name' },
		          { field: 'subject', title: 'Subject' },
                  { field: 'fromName', title: 'From Name' },
		          { field: 'fromAddress', title: 'From Email' },
                  { field: 'active', title: 'Active', template: '#if( active == 1 ) { #Yes# } else { #No# }#' }],
		change: function()
		{
			EmailList.setSelected( this.select() );
		},
		dataBound: function()
		{
			EmailList.setSelected( this.select() );
		}
	});
}

EmailList.setSelected = function( selectedRows )
{
	if( selectedRows.length == 1 )
    {
    	$( '#editButton' ).removeClass( 'k-state-disabled' );
    }
    else
    {
    	$( '#editButton' ).addClass( 'k-state-disabled' );
    }
    
    if( selectedRows.length > 0 )
    {
    	$( '#deleteButton' ).removeClass( 'k-state-disabled' );
    }
    else
    {
    	$( '#deleteButton' ).addClass( 'k-state-disabled' );
    }
}

EmailList.addGridListeners = function()
{
	$( 'table' ).dblclick( EmailList.editEmail );
	$( '#header-search' ).keyup( EmailList.filterGrid );
	$( '#header-search-icon' ).click( EmailList.filterGrid );
}

EmailList.editEmail = function()
{
	var selected = EmailList.getGrid().dataSource.getByUid( EmailList.getGrid().select().data().uid );
	_emailId = selected.emailId;
	
	$( '#mainContentDiv' ).load( "/ajax/emailForm" );
}

EmailList.filterGrid = function()
{
	EmailList.getGrid().dataSource.filter({});
}

EmailList.getGrid = function()
{
	return $( '#emailGrid' ).data( 'kendoGrid' );
}

$( document ).ready( function()
{
	EmailList.setGrid();
	EmailList.addGridListeners();
});