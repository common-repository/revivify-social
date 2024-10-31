( function( $ ) {

    (function (d, s, id) {
        var js, fjs = d.getElementsByTagName(s)[0];
        if (d.getElementById(id)) return;
        js = d.createElement(s); js.id = id;
        js.src = "https://connect.facebook.net/en_US/sdk.js";
        fjs.parentNode.insertBefore(js, fjs);
    }(document, 'script', 'facebook-jssdk'));
		
    $( document ).ready( function() {

		var weekdayTimes=[];
		
		var gas="";
		$.fn.serializeFormJSON = function () {
			var o = {};
			var a = this.serializeArray();
			$.each(a, function () {
				if (o[this.name]) {
					if (!o[this.name].push) {
						o[this.name] = [o[this.name]];
					}
					o[this.name].push(this.value || '');
				} else {
					o[this.name] = this.value || '';
				}
			});
			return o;
		};		
			
		$( ".reset_general_settings" ).click(function() {
			event.preventDefault();
			var button = $( this );	
			var data = {
				'action' : 'sss_general_processing',
				'nonce'  : button.data('nonce'),		
				'type' : button.data('type'),				
				'data' : ( button.data('type')=="reset_settings" ) ? $('#general_settings').serializeFormJSON() : $('#posts_settings').serializeFormJSON(),
				'type' : button.data('type')
			};
			
			$.post( settings.ajaxurl, data, function( response ) {
				if(!response.success){
					Notification("Something went wrong during reset operation");
					return;
				}	
				$("#btn_search_posts").click();
			});			
		});			
		
		$( '#General' ).on( 'click', '.save_general_settings', function( event ) {
			event.preventDefault();
			var $button = $( this );			
			var selected=0;
			$('.weekdays').each(function(index, element) {
				if (element.checked == true)
					selected = selected | element.getAttribute('data-value');
			});

			var data = $( $('#general_settings') ).serializeFormJSON();

			var times = "";
			if (weekdayTimes.value != undefined)
				weekdayTimes.value.forEach(value => {
					if (times == "")
						times = value.value
					else
						times += "," + value.value
				});

			var exclusion = "";
			if(tagify != undefined)
				tagify.value.forEach(value => {
					if (exclusion == "")
						exclusion = value.tid
					else
						exclusion += "," + value.tid
				});

			data["weekday_times"] = times;
			data["share_type"] = $("#share_type").val();
			
			var data = {
				'action' : 'sss_general_processing',
				'post_id': $button.data( 'post_id' ),
				'nonce'  : $button.data('nonce'),		
				'type' : "general",				
				'data' : data,
				'report' : $( '.report-a-bug-message' ).val()
			};

			$.post( settings.ajaxurl, data, function( response ) {
					if(!response.success){
						Notification("Something went wrong during save operation");
						return;
					}else
						Notification("Page details saved");
			});			
		});	
		
		$( "#enable_pinger" ).click(function() {
			if ($(this).is(":checked"))
				$("#pinger_box").show();
			else
				$("#pinger_box").hide();
		});

		$( '#General').on( 'change', '#select_account', function( event ) {
			var selected = $(this).val();
			if ( selected == "Select"){
				$("#post_list > tbody").html("");
				$(".reset_general_settings").hide();
				$("#general_section").hide(); 
				$("#select_page").hide();
				
				if(  $(this).parents("#General").length != 1)
					$("#posts_content").hide();
				return;
			}
			
			if(selected != null)
				$(".tablinks").prop('disabled', false);
			
			$(".reset_general_settings").show();
			var selector = "";			

			$("#select_page").empty();
			var data = {
				'action' : 'sss_general_processing',					
				'nonce'  : $(this).data('nonce'),		
				'type' : "sss_get_pages",				
				'data' : {
					'id' : selected
				}
			};
				
			$.post( settings.ajaxurl, data, function( response ) {
				if(!response.success){
					Notification("Something went wrong, can't get the pages");
					return;
				}
				
				selector="#General #select_page";
				
				response.data.forEach( function(page){
					$(selector).append('<option value='+page["id"]+'>'+ page["name"] +'</option>');
					$(selector).show();
				});
				
				$("#select_page").trigger("change");
			})
		});	
		
		
		$( '#Posts').on( 'change', '#select_account_posts', function( event ) {
			var selected = $(this).val();
			if ( selected == "Select"){
				$("#post_list > tbody").html("");
				$(".reset_general_settings").hide();
				$("#general_section").hide();
				$("#select_page").hide();
				
				if(  $(this).parents("#General").length != 1)
					$("#posts_content").hide();
				return;
			}
			$(".reset_general_settings").show();
			var selector = "";
					
			$("#posts_content").show();
		});		
		
		$("#select_account").trigger("change");	
		$("#select_account_posts").trigger("change");	
		
		var jqTagify;
		var tagify;

				
		$( '#General' ).on( 'change', '#select_page', function( event ) {
			$("#general_section").hide();
			gas = $(this).val();

			$('#general_settings input[type="text"]').val('');
			$("textarea").val("");
			$(":checkbox").attr("checked", false);
			
			var selected = $("#select_account").val();
			var selectedPage = $(this).val();
			if (selectedPage == "-"){
				$("#general_section").hide();
				return;
			}

			if(!selected || !selectedPage)
				return;
			
			if ( selected != "Select"){
				var data = {
					'action' : 'sss_general_processing',
					'nonce'  : $(this).data('nonce'),		
					'type' : "general_account",				
					'data' : {						
						'id' : selected,
						'page' : selectedPage
					}
				};
				
				if(tagify != undefined)
					tagify.removeAllTags();
				
				$.post( settings.ajaxurl, data, function( response ) {					
					if(!response.success){
						Notification("Something went wrong while retriving the options for this page");
						return;
					}
					
					$('.weekdays').each(function(index, element) {
						element.checked = false;
					});

					for(var k in response.data.options){
						// remove_on_delete , enable_pinger, pinger_target
						if (k == "weekdays"){
							$('.weekdays').each(function(index, element) {								
								if ( response.data.options[k] & element.getAttribute('data-value') )
									element.checked = true;
							});							
						}else
						if (k == "weekday_times"){
							var times = response.data.options[k].toString().split(",");
							var output = "";
							times.forEach(value => {
								if (value != "") {
									var hours = Math.floor(value / 60);          
									var minutes = value % 60;		
									if ( output == "" )
										output += (hours<10 ? "0" + hours : hours) + ":" + (minutes<10 ? "0" + minutes : minutes);
									else
										output += "," + (hours<10 ? "0" + hours : hours) + ":" + (minutes<10 ? "0" + minutes : minutes);
								}
							});
							$("#" + k).val( output );
						}else
						if($("#" + k).is(':checkbox') )
							$("#" + k).prop("checked", response.data.options[k]==0 ? false : true );							
						else
						if($("#" + k).is(':radio') )
							$("#" + k).prop("checked", response.data.options[k]==0 ? false : true );						
						else
							$("#" + k).val(response.data.options[k]); 
					}

					$("#remove_on_delete").prop("checked", (response.data["remove_on_delete"] == null || response.data["remove_on_delete"]==0) ? false : true );
					$("#general_section").show();
					var event = new Event('change');
					
					
					weekdayTimes = $('#weekday_times').tagify().data('tagify');
					$("#time-picker").hunterTimePicker();
					
				});	
			}else
				$("#general_section").hide();
		});		
								
		$( '#addTime' ).on( 'click', function( event ) {
			event.preventDefault();
			weekdayTimes.addTags( $("#time-picker").val() );			
		});
		
		$( '#ActionLogs' ).on( 'click', '.reset_action_log', function( event ) {
			var data = {
				'action' : 'sss_general_processing',
				'nonce'  : $(this).data('nonce'),
				'type' : 'reset_action_log'
			};
			$.post( settings.ajaxurl, data, function( response ) {
				if(!response.success){
					Notification( "Something went wrong while reseting action log" );
					return;
				}				
				$("#action_log > tbody").html("");
			});
		});
		
		// ************ SEARCH POSTS *****************
		
		$( '#Posts' ).on( 'click', '.btn_share_now', function( event ) {
			var postID = $(this).data('id');	
			var acc = $("#select_account_posts").val();
			var btn = $(this);
			var data = {
				'action' : 'sss_general_processing',
				'nonce'  : $(this).data('nonce'),
				'operation'  : $(this).data('operation'),
				'type' : 'share_now',
				'data' : {
					'postID' : postID,
					'accountID' : acc
				}
			};

			document.getElementById("rvs_spinner_center").style.display="block";	
			document.getElementById("popup").style.display="none";
			document.getElementById("rvs_toggle_div").style.display="block";
			
			$.post( settings.ajaxurl, data, function( response, status ) {				
				Notification( response["data"] );
			});	
			return false;
		});
		
		$( '#Posts' ).on( 'click', '.btn_exclude_post,.btn_include_post', function( event ) {	
			var postID = $(this).attr("id");
			var acc = $("#select_account_posts").val();

			if (acc == "Select"){
				Notification( "Please select the page" );
				return;
			}

			var data = {
				'action' : 'sss_general_processing',
				'nonce'  : $(this).data('nonce'),				
				'type' : 'include_exclude_post',
				'data' : {
					'operation'  : $(this).data('operation'),
					'postID' : $(this).data('id'),
					'account' : acc
				}
			};

			var btn = this;

			$.post( settings.ajaxurl, data, function( response ) {
				if(!response.success){
					Notification( "Something went wrong while excluding post" );
					return;
				}

				switch($(btn).data('operation')){
					case "exclude":
						$(btn).data('operation', "include"); 
						$(btn).html("Include");
					break;
					case "include":
						$(btn).data('operation', "exclude"); 
						$(btn).html("Exclude");
					break;
					case "genexclude":
						$(btn).data('operation', "geninclude"); 
						$(btn).html("Global Include");
					break;
					case "geninclude":
						$(btn).data('operation', "genexclude"); 
						$(btn).html("Global Exclude");
					break;
				}
			});		
			return false;
		})

		function SearchPosts(term, cat, page, loadMore){
			var acc= $("#select_account_posts").val();

			if (acc == "Select"){
				Notification( "Please select the page" );
				return;
			}

			var data = {
				'action' : 'sss_general_processing',
				'nonce'  : $("#btn_search_posts").data('nonce'),		
				'type' : "sss_get_posts",				
				'data' : {'search' : term,
						'catID' : cat,
						'page' : page,
						'account' : acc
						}
			};

			$.post( settings.ajaxurl, data, function( response ) {
				if(!response.success)
					Notification( "Something went wrong with Search Operation" );
				else{
					$(".load_more_posts").prop('disabled',false);
					if(!loadMore)
						$("#post_list > tbody").html("");

					$.each( response.data["posts"], function( index, post ) {
						
						var share="<button id='s"+post[0]+"' name='"+post[0]+"' data-operation='share' data-id='"+post[0]+"' data-nonce='" + response.data["n"] +"' class='btn_share_now'> Share Now </button>";
						var gen="<button id='g"+post[0]+"' name='"+post[0]+"' data-operation='genexclude' data-id='"+post[0]+"' data-nonce='" + response.data["n"] +"' class='btn_exclude_post'> Global Exclude </button>" + share + (( post[4] != "" ) ? ("<br/><small>Shared in: " + post[4] + "</small>") : "");
						
						if ( post[3] == 1)
							gen = "<button id='g"+post[0]+"' name='"+post[0]+"' data-operation='geninclude' data-id='"+post[0]+"' data-nonce='" + response.data["n"] +"' class='btn_exclude_post'> Global Include </button>" + share + (( post[4] != "" ) ? ("<br/><small>Shared in: " + post[4] + "</small>") : "");
							
						if ( post[2] == 0)					
							$("#post_list tbody").append("<tr><td>" + post[1] + "</td><td>" +
								"<button id='l"+post[0]+"' name='"+post[0]+"' data-operation='exclude' data-id='"+post[0]+"' data-nonce='" + response.data["n"] +"' class='btn_exclude_post rv-mr-2'> Exclude </button>"+
								gen + 
							"</td></tr>");
						else
							$("#post_list tbody").append("<tr><td>" + post[1] + "</td><td>"+
								"<button id='l"+post[0]+"' name='"+post[0]+"' data-operation='include' data-id='"+post[0]+"' data-nonce='" + response.data["n"] +"' class='btn_include_post'> Include </button>"+
								gen +
							"</td></tr>");
					});					
				}
			});						
		}
		
		var search_pagination=1;
		$('#post_search' ).on("input", function() {
			var search = this.value;
			var catID = $("#post_category").val();
			$("#post_list > tbody").html("");
			search_pagination=1;
			SearchPosts(search, catID, search_pagination, false);			
		});
		
		$("#post_category").change(function() {
			var search = $('#post_search' ).val();
			var catID = $("#post_category").val();				
			SearchPosts(search, catID, search_pagination, false);
		});
		
		$( '#Posts' ).on( 'click', '#btn_search_posts', function( event ) {
			$("#post_list > tbody").html("");
			var search = $('#post_search' ).val();
			var catID = $("#post_category").val();						
			search_pagination = 1;
			SearchPosts(search, catID, search_pagination, true);		
			return false;
		});
		
		$( '#Posts' ).on( 'click', '.load_more_posts', function( event ) {
			var search = $('#post_search' ).val();
			var catID = $("#post_category").val();						
			search_pagination += 1;
			SearchPosts(search, catID, search_pagination, true);
		});
		
		// **********************************************************			
		
		$( '#scheduler' ).on( 'click', function( event ) {
			var action = "sss_cron_deactivate";
			var button = $( this );
			if( button.is(':disabled') )
				return;
			
			button.prop("disabled", true);
			
			if ( button.data('type') == "start")
				action = "sss_cron_activate";					
				
			var data = {
				'action' : action,
				'nonce'  : button.data('nonce')
			};
		
			$.post( settings.ajaxurl, data, function( response ) {
				if(!response.success){
					Notification( "Something went wrong with scheduler Start/Stop" );
					button.prop("disabled", false);
					return;
				}
				
				if ( button.data('type') == "start"){
					button.removeClass("rv-btn-general");
					button.addClass("rv-btn-accent");
					button.data('type', 'stop');
					button.html('<i class="fa fa-pause" aria-hidden="true"></i> Stop');
				}
				else{
					button.removeClass("rv-btn-accent");
					button.addClass("rv-btn-general");
					button.data('type', 'start');
					button.html('<i class="fa fa-play" aria-hidden="true"></i> Start');					
				}
				button.prop("disabled", false);
			});	
		});
    });

	var w;

	function popupwindow(url, title, w, h) {
		var left = Math.round((screen.width/2)-(w/2));
		var top = Math.round((screen.height/2)-(h/2));
		return window.open(url, title, 'toolbar=no, location=no, directories=no, status=no, '
				+ 'menubar=no, scrollbars=yes, resizable=no, copyhistory=no, width=' + w 
				+ ', height=' + h + ', top=' + top + ', left=' + left);
	}

	$('.save_api').click(function(){
		$(this).prop("disabled", true);
		var data = {
			'action' : 'sss_general_processing',
			'nonce'  : $(this).data('nonce'),	
			'data' : {
				'apiKey' : $("#apiKey").val()
			},
			'type' : "save_api"
		};
		$.post( settings.ajaxurl, data, function( response ) {
			if(response.success){
				window.location=document.location.href;
			}
		});
	});
	
		//********************************************
		//AUTHORIZE FACEBOOK
		//********************************************

		$('.clear_main,.clear_additional,.clear_fb,.clear_tw').click(function(){
			var removeBtn = $(this);
			removeBtn.prop("disabled", true);

			var target = $(this).data( 'target' );
			var accid = $(this).data( 'accid' );			
			
			var data = {
				'action' : 'sss_general_processing',
				'nonce'  : $(this).data('nonce'),	
				'data' : {
					'target' : target,
					'accid' : accid,
				},
				'type' : "remove_accounts"
			};
						
			$.post( settings.ajaxurl, data, function( response ) {
				if(response.success){
					$(removeBtn).prop("disabled", false);	
					window.location=document.location.href;

					$("[id='select_account'] option[value='" + accid + "']").remove();
					$("[id='select_account']").val('Select').change();
				}
			});
		});
		
		$('.add_acc_fb,.add_acc_tw').click(function(){
			event.preventDefault();
			$(this).prop("disabled", true);
			
			var action_type = $(this).data( 'addacc' ) ;
			var nonce = $(this).data('nonce');
			var url = $(this).attr('value');	
			var redirectionReceived=false, resultReceived=false;
			w = popupwindow("https://www.revivify.social/account/wplogout", "Adding social account", "600", "800");

			w.beforeunload = function (){
				if (this.hasData){
				}
			}

			window.addEventListener("message", function(ev) {
				if (!redirectionReceived && ev.data.message === "deliverPermission") {
					redirectionReceived=true;
					w.location.href=url;
				}
				if (!resultReceived && ev.data.message === "deliverResult") {
					resultReceived=true;
					var accountDetails = ev.data.result;

					switch(action_type){
						case "setfb": case "settw":
							accountDetails["priority"] = "main";					
						break;
						case "addfb": case "addtw":
							accountDetails["priority"] = "second";	
						break;
					}

					if( action_type=="setfb" || action_type=="settw")
						$('#apiKey').val( accountDetails["apikey"] );

					var data = {
						'action' : 'sss_general_processing',
						'nonce'  : nonce,	
						'action_type' : action_type,
						'type' : "accounts",				
						'data' : accountDetails
					};


					if(accountDetails=="failed"){
						Notification( "Something went wrong while connecting your account. You might be using wrong API Key or other details" );
						ev.source.close();
					}else
					$.post( settings.ajaxurl, data, function( response ) {

						if(!response.success){
							Notification( "Something went wrong during 'add account' operation" );
							//return;
						}	
					   	ev.source.close();
					    	//window.location=document.location.href;
					});									
				}
			});

			var interval;
			function explode(){
				if (!resultReceived)
					w.postMessage({ message: "requestResult" }, "*");
				if (w.closed){
					//console.log("Social add complete. Closed");	
					clearInterval(interval);
					if(! $('#popup').is(':visible'))
						window.location.reload();
			   }	
			}
			interval = setInterval(explode, 1000);		
			//w.focus();							
		});
		
		$('.saveFBKeys,.saveTWKeys').click(function(){			
			event.preventDefault();
			switch( $(this).data('target')){
				case "saveFBKeys":
					FB.init(
						{
							appId      : $("#apiKeyFB").val(),
							autoLogAppEvents : true,
							xfbml            : true,
							version          : 'v7.0'
						}						
					);
						
					var acc = {};
					var nonce = $(this).data('nonce');
					
					var logCheckOK=true;
					FB.getLoginStatus(function(response) {				
						if(response.status=="unknown"){
							Notification( "Something went wrong (e.g. wrong API Key)" );
							logCheckOK=false;
						}else{

						}
					});
				
					if(!logCheckOK)	return;
					
					FB.login((loginResponse)=>{                                                          
						if(loginResponse.authResponse)
						{
							acc["id"] = loginResponse.authResponse.userID;
							acc["type"] = "fb";
							acc["pages"] = [];

							FB.api('/me', { locale: 'en_US', fields: 'name, email' }, function(response){ 
								acc["email"] = response.email;
							});

							FB.api('/me/accounts', function(response){ 
								response.data.forEach(p => {
									page = {};
									page["id"] = p.id;
									page["name"] = p.name;									
									page["access_token"] = p.access_token;
									page["options"] = {};
									acc["pages"].push(page);
								});				

								acc["apiKey"] = $("#apiKeyFB").val();
								acc["apiSecretKey"] = $("#apiSecretKeyFB").val();
								acc["priority"] = "own";
								
								var data = {
									'action' : 'sss_general_processing',
									'nonce'  : nonce,	
									'type' : "accounts",				
									'data' : acc
								};
					
								$.post( settings.ajaxurl, data, function( response ) {
									if(!response.success){
										Notification( "Something went wrong with Facebook connection" );
										return;
									}else
										window.location.reload();
								});
							});
						} else {
							
						}
					},{scope: 'publish_pages,manage_pages,email', return_scopes: true});	
				break;
				case "saveTWKeys":
					var data = {
						'action' : 'sss_general_processing',
						'nonce'  : $(this).data('nonce'),	
						'type' : "own_accounts_tw",
						'data' : {"key": $("#apiKeyTW").val() , "secret" : $("#apiSecretKeyTW").val() }						
					};
					
					var nonce = $(this).data('nonce');
					
					$.post( settings.ajaxurl, data, function( response ) {
						if(!response.success){
							Notification( "Something went wrong with Twitter connection. Check your keys." );
							return;
						}
						
						var w = popupwindow(response["data"], "Twitter Login", 500, 500);
						var interval;
						
						function explode(){				
							try{
								var obj = JSON.parse(w.document.body.textContent);
								if(obj.status == 1){									
									var acc = {};									
									acc["id"] = obj.id;
									acc["email"] = obj.email;
									acc["type"] = "tw";
									acc["pages"] = [];									
									page = {};
									page["id"] = obj.id;
									page["name"] = obj.name;
									page["options"] = {};
									acc["pages"].push(page);
									acc["apiKey"] = obj.key;
									acc["apiSecretKey"] = obj.secret;									
									acc["apiAccessKey"] = obj.access_key;
									acc["apiAccessSecretKey"] = obj.access_secret;										
									acc["priority"] = "own";
									
									var data = {
										'action' : 'sss_general_processing',
										'nonce'  : nonce,	
										'type' : "accounts",				
										'data' : acc
									};

									$.post( settings.ajaxurl, data, function( response ) {
										if(!response.success){
											Notification( "Something went wrong with Twitter connection to ReVivify." );
											return;
										}else
											window.location.reload();
									});									
									
									w.close();
								}
							}catch(error){
								
							}
							
							if (w.closed){
								//console.log("Social add complete. Closed");	
								clearInterval(interval);
								window.location.reload();
						   }	
						}
						interval = setInterval(explode, 1000);							
					});	
				break;
			}
		});
			
		$('#refresh_action_log').click(function(){			
			var data = {
				'action' : 'sss_general_processing',
				'nonce'  : $(this).data('nonce'),
				'type' : 'refresh_action_log'
			};
			$.post( settings.ajaxurl, data, function( response ) {
				if(!response.success){
					Notification( "Something went wrong while reseting action log" );
					return;
				}				

				$("#action_log > tbody").html("");
				$.each( response.data, function( index, action ) {
					$("#action_log tbody").append("<tr><td>" + action["time"] + "</td><td>"  + action["action"] + "</td></tr>");
				});
			});			
		});

			
		  
		$(".rv-js-modal-close").click(function() {			
			document.getElementById("rvs_toggle_div").style.display="none";
		});		

		function PopupPosition(){			
			var offset = $(document).scrollTop();
			var viewportHeight = $(window).height();	
			$('#popup').css('top', (offset  + (viewportHeight/2)) - ($('#popup').outerHeight()/2));
			$('#rvs_spinner_center').css('top', (offset  + (viewportHeight/2)) - ($('#popup').outerHeight()/2));
		}
		
		$( window ).scroll(function() {
			PopupPosition();
		});
		
		function Notification(message){
			document.getElementById("rvs_spinner_center").style.display="none";
			document.getElementById("popup").style.display="block";
			document.getElementById("rvs_toggle_div").style.display="block";	
			$("#notificationBody").text(message);
			$('#popup').fadeIn($('#popup').data());
			PopupPosition()	
		}		

})( jQuery );



function OpenOption(evt, cityName) {
	var i, tabcontent, tablinks;
	tabcontent = document.getElementsByClassName("tabcontent");
	for (i = 0; i < tabcontent.length; i++) {
		tabcontent[i].style.display = "none";
	}
	tablinks = document.getElementsByClassName("tablinks");
	for (i = 0; i < tablinks.length; i++) {				
		tablinks[i].className = tablinks[i].className.replace("rv-tab-btn", "rv-btn");
	}

	document.getElementById(cityName).style.display = "block";
	evt.currentTarget.className = evt.currentTarget.className.replace("rv-btn", "rv-tab-btn");
}
