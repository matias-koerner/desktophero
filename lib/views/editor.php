<style type="text/css">
	body {
		overflow: hidden;
		color: #000;
		background-color: #000;
		margin: 0px;
	}

	.bg-inverse-custom { margin-top:  auto; }
	.container {max-width: 100%; width: 1140px;}

	
</style>

<div class="bg-inverse text-center center-vertically" role="banner">
  <div class="editor-container">
	<div id="threejs-demo"> </div>
	<div id="editor"> </div>
  </div>
</div>


<script>
var bodyMap = {};  //an object with a persistent rendering of the current model configuration as defined in the UI
var modelMap = {};  //an object iwth a persistent list of the models available for each attachment / category
modelMap.presets = {};  //object that contains the presets data
modelMap.mids = {}; //object that sorts the models by their model id, duplicate of the data in the attachment/category object, just organized differently
modelMap.tags = {}; //object that lists the model ids inside each tag id.  updated whenever a query is made against an empty tag

s3FormDetails = {};

figure = {};       //object that will have all our figure modification methods in it
figure.id = null;  //all the defaults
figure.figure_name = "";
figure.figure_data = "";
figure.figure_story = "";
figure.figure_description = "";
figure.figure_automatic_description = "";
figure.photo_render = "";
figure.photo_inspiration = "";
figure.photo_thumbnail = "";
figure.flag_nsfw_sex = 0;
figure.flag_nsfw_violence = 0;
figure.flag_nsfw_other = 0;
figure.flag_deleted = 0;
figure.flag_hidden = 1;
figure.flag_featured = 0;
figure.flag_private = 1;
figure.flag_date_created = Math.floor(Date.now() / 1000);
figure.flag_date_updated = Math.floor(Date.now() / 1000);
figure.count_downloads = 0;
figure.count_views = 0;
figure.editable = ["figure_name","figure_data","figure_story","figure_description","figure_automatic_description","photo_thumbnail","photo_inspiration","photo_render","flag_nsfw_sex","flag_nsfw_violence","flag_nsfw_other","flag_private","flag_featured","flag_hidden","flag_deleted"]; //this isn't secure, it's convenient.  security is handled serverside

user = {};			//object that will have the (untrusted) user information in it

//for uploader
filesUploaded = [];
folders = [];


//SETUP THE FIGURE METHODS
figure.id = <?php echo $figure_id; ?>;
user.id = <?php echo $user_id; ?>;

//gets encrypted magic form data for uploading images and models
$.get("/api/v1/uploads",function(data){
	s3FormDetails = data;
},"json");


figure.create = function(){

	//POST a new model to get an ID
	$.ajax({
		type: "POST",
		url: '/api/v1/figure',
		data: {},
		success: function(data) {
			//Set the new ID to the returned figureID
			figure.id = data.id;

			//reset the UI to default values
			figure.refreshUI();
		},
		async: false,
		dataType: "json"});
	
};
figure.get = function(){

	//GET an existing model
	$.ajax({
		type: "GET",
		url: "/api/v1/figure/"+figure.id,
		success: function(data) {

			//go through each field in the editable list and stash the data in our figure object
			for (var i in figure.editable) {
				k = figure.editable[i];
				figure[k] = data[k];
			}
			//make sure the UI matches the truth in the figure object
			uilog("Figure Loaded");
			figure.refreshUI();
		},
		async: false,
		dataType: "json"
	});
};
figure.update = function(){
	
	//PUT to update an existing model will new data from the figure object
	//Filter the things out of figure that we don't want to send to the server
	var out = {};
	for (var i in figure){
		if (figure.editable.indexOf(i) !== -1 ) {
			out[i] = figure[i];
		}
	}

	$.ajax({
		type: "PUT",
		headers: {"X-HTTP-Method-Override": "PUT"},
		url: '/api/v1/figure/' + figure.id,
		contentType: "x-www-form-urlencoded",
		data: out,
		success: function(data) {
			uilog("Figure Data Sent To Server.");
			figure.refreshUI();
		},
		async: false,
		dataType: "json"});

};
figure.delete = function(){

};
figure.refreshUI = function(){

	//all text and text areas
	$("[data-bind]").each( function(i){
		var key = $(this).data('bind');
		$(this).val( figure[key] );
	});

	//all photos
	$("[data-photo]").each( function(i){
		var key = $(this).data('photo');
		$(this).attr("src", figure[key] );
	});

	//all file uploads should be blanked out
	$('[type="file"]').val("");

	//remove old file upload bars
	$('.progress-bar-area').empty();


	//TODO Make sure FigureSetup panel is updated
	//TODO Make sure library panel is updated

};
figure.screenCap = function(cb){
	//Take a screencap of the model for our purposes
	var data =  dataURItoBlob( window.view.renderer.domElement.toDataURL("image/png") );
	//var data =  window.view.renderer.domElement.toDataURL("image/png");
	var __file_key = "captures/"+ user.id + "." + figure.id + "screencap.png";

	//setup the form with the magic input
	var formData = new FormData();
	formData.append('key', __file_key);
	formData.append('AWSAccessKeyId', s3FormDetails.AWSAccessKeyId);
	formData.append('acl', s3FormDetails.acl);
	formData.append('policy', s3FormDetails.policy);
	formData.append('signature', s3FormDetails.signature);
	formData.append('Content-Type', "image/png");
	formData.append('success_action_redirect', s3FormDetails.success_action_redirect);
	formData.append('file', data);

	//send it off to S3
	$.ajax({
		url: s3FormDetails.url,
		type: "POST",
		async: false,
		data: formData,
		contentType: false,
		processData: false,
		success: function(){
			figure.photo_render = "https://desktop-hero.s3.amazonaws.com/" + __file_key;
			uilog("Screen Cap created");
			
			//if a callback fuction is specified, run it (see figure.save)
			if (cb) { cb(); }
		}
	});
};

//when you click the save button
figure.save = function() {

	//first get and save a screencap to s3
	figure.screenCap( function(){
		//then when that is done, call the update process
		figure.update();
	});
	uilog("Figure Saved");
};

//Check to see if we are editing a model or creating a new one
if (figure.id) {

	uilog("Geting your figure!");
	//TODO: create load model process
	figure.get();
	//Load it into the canvas
	uilog("Editing Figure ID:" + figure.id);

} else {

	uilog("Creating new figure for you!");
	figure.create();
	uilog("New Figure ID:" + figure.id);
	
}


//works for things in a flat list with the tag table name currently
var getSimpleItems = function(url,target){
	$.getJSON(url, function( tags ){
		//Get all the Genre Tags.  These will be used as macro filters for all the other lists
		var slides = "";
		$.each( tags, function(k,v){
			slides += "<div class='mini-select col-md-3' data-tag-id='" +v.id+ "'> <img src='" +v.thumbnail+ "' alt='" + v.tag_hint + "'><span class='label'>"+ v.tag_label +"</span></div>\n" ;
		});

		$("#"+target).html(slides);

	});
};

var getModelsForTags = function(url, tid, cb) {
	
	//if the tag id isn't in the data model yet, then get it
	if (! (tid in modelMap.tags) ) {
		$.getJSON(url, function(tags){
			var modelsForTags = [];
		
			$.each(tags, function(k,v){
				modelsForTags.push(v.model_id);
			});

			modelMap.tags[tid] = modelsForTags;
			
		}).always(function(){
			//once the model is full, execute the callback function requested and exit
			if(cb) cb(tid);
			return;
		});			
	} else {
		//if we already have the tags, then just execute the callback funciton
		if(cb) cb(tid);	
	}
	
};

//works for models
var getTabbedItems = function(url,target,key) {
	
	$.getJSON(url, function( data ){
	
		
		var tabs = "<ul class='nav nav-tabs' id='"+target+"-tabs'>";
		var content = "<div class='tab-content clearfix'>";
		modelMap[key] = data[key];  //add or reset the attachment key for the persistent model, eg "head"

		$.each( data[key], function(k,v){
			//make a new tab
			var slides = "";

			tabs += "<li role='presentation' class='nav-item'><a class='nav-link' href='#panel-"+v[0].model_category+"' data-toggle='tab'>"+ v[0].model_category +"</a></li>";
			var panel = "<div class='tab-pane' id='panel-"+ v[0].model_category+"' role='tabpanel'>";

			$.each( v, function(kk,vv){
				//make a new slide
				slides += "<div class='mini-select col-md-3' data-model-id='" +vv.id+ "'> <img src='" +vv.photo_thumbnail+ "' alt='" + vv.model_name + "'><span class='label'>"+ vv.model_name +"</span></div>\n";
				modelMap.mids[vv.id] = vv;
			});

			content += panel += slides += "</div>";
		});

		tabs += "</ul>";
		content += "</div>";
		tabs += content;

		$("#"+target).html(tabs);
		$("#"+target + "-tabs li a").click(function (e) {
			e.preventDefault();
			$(this).tab('show');
		});
		$("#"+target + "-tabs li:first a").tab('show');
	});
};

//get the presests from the table and lays them out with some special rules
var getPresets = function() {
	$.getJSON("/api/v1/preset/all", function( presets ){
		//Get all the Presets.  These will sort into a morph target tab
		
		var tabs = "<ul class='nav nav-tabs' id='editor-presets-data-tabs'>";
		var content = "<div class='tab-content clearfix'>";
		modelMap.presets.morph = presets.morph;

		$.each( presets.morph, function(k,v){
			//make a new tab
			var slides = "";

			tabs += "<li role='presentation' class='nav-item'><a class='nav-link' href='#panel-"+v[0].preset_category+"' data-toggle='tab'>"+ v[0].preset_category +"</a></li>";
			var panel = "<div class='tab-pane' id='panel-"+ v[0].preset_category+"' role='tabpanel'>";

			$.each( v, function(kk,vv){
				//make a new slide
				slides += "<div class='mini-select col-md-3' data-tag-id='" +vv.id+ "'> <img src='" +vv.photo_thumbnail+ "' alt='" + vv.preset_name + "'><span class='label'>"+ vv.preset_name +"</span></div>\n";
			});

			content += panel += slides += "</div>";
			
		});

		tabs += "</ul>";
		content += "</div>";
		tabs += content;

		$("#editor-presets-data").html(tabs);
		$('#editor-presets-data-tabs li a').click(function (e) {
			e.preventDefault();
			$(this).tab('show');
		});
		$("#editor-presets-data-tabs li:first a").tab('show');
	});
};



$(document).ready( function(){

	//we know there's going to be at least a few seconds of loading.  this will prevent flicker while the scene renders\
	//TODO Make this sensitive to the onLoadComplete handler from three.js or whatever it's called
	loader.show();
	setTimeout(function(){ loader.hide(); }, 400);

	//ATTACH SPINNERS TO AJAX EVENTS
	$(document)
	  .ajaxSend(function () {
			loader.show();
	  })
	  .ajaxComplete(function () {
			loader.hide();
	});

	//keep it all using the REST apis rather than a combination of internal and external functions
	//TODO: turn these into knockout modules if it makes sense

	//Create a running narrative of what's happening for users in the UI console
	clearuilog();

	//now tha all the HTML is present, make sure to refresh the UI
	figure.refreshUI();

	//GET ALL GENRE TAGS
	getSimpleItems("/api/v1/tags/by/genre","editor-genre-data");

	//GET ALL PRESETS
	getPresets();

	//GET ALL HEAD MESHES
	getTabbedItems("/api/v1/model/by/head/mesh","editor-head-data","head");

	//GET ALL ARMS MESHES
	getTabbedItems("/api/v1/model/by/arms/mesh","editor-arms-data","arms");

	//GET ALL HANDS MESHES
	getTabbedItems("/api/v1/model/by/hands/mesh","editor-hands-data","hands");

	//GET ALL CHEST / UPPER BODY MESHES
	getTabbedItems("/api/v1/model/by/chest/mesh","editor-chests-data","chest");

	//GET ALL LEGS / LOWER BODY MESHES
	getTabbedItems("/api/v1/model/by/legs/mesh","editor-legs-data","legs");

	//GET ALL FEET and FOOTWare BODY MESHES
	getTabbedItems("/api/v1/model/by/feet/mesh","editor-feet-data","feet");

	//GET ALL FEET and FOOTWare BODY MESHES
	getTabbedItems("/api/v1/model/by/base/mesh","editor-bases-data","base");

	//GET ALL POSES
	getTabbedItems("/api/v1/model/by/pose/pose","editor-poses-data","pose");

	// ##################################################################

	document.getElementById('loadCharacter').addEventListener('change', readCharacterFile, false);

	document.getElementById('loadPose').addEventListener('change', readPoseFile, false);

	document.getElementById('loadAsset').addEventListener('change', readAssetFile, false);

	function readCharacterFile (evt) {
		var files = evt.target.files;
		var file = files[0];
		var reader = new FileReader();
		reader.onload = function() {
			model.character.clear();
			model.loadJSONPreset(this.result);
		}
		reader.readAsText(file);
	}

	function readAssetFile (evt) {
		var files = evt.target.files;
		var file = files[0];
		var filename = file['name'];
		var match = filename.match(/\.[^/.]+$/);
		var name = filename.substring(0, match.index);
		var extension = filename.substring(match.index);
		var reader = new FileReader();

		reader.onload = function() {
			var contents = this.result;

			var geometry;
			if (extension == '.json' || extension == '.js'){
				var json = JSON.parse(contents);
				var results = new THREE.JSONLoader().parse(json);
				geometry = results['geometry'];
			} else if (extension == '.stl'){
				geometry = new THREE.STLLoader().parse(contents);
				geometry = new THREE.Geometry().fromBufferGeometry(geometry);
				geometry.rotateX(-Math.PI/2);
			} else {
				sweetAlert({
					title: '',
					html: 'Filetype $(extension) is not supported. Supported filetypes are: .js, .json, .stl'
				});
				return;
			}

			model.libraries.get('default').addMesh(name, '', 'custom', [], geometry);
			sweetAlert({
				title: '',
				html: 'Mesh "' + name + '" has been successfully loaded. <br> Look for it in the mesh library under "custom".'
			});
			var uid = view.selectedMesh.boneGroupUid;
			view.libraryClearMeshes();
			view.libraryPopulateMeshes(uid);
			view.showLibrary('mesh');
		};

		if (extension == '.json' || extension == '.js'){
			reader.readAsText(file);
		} else if (extension == '.stl'){
			reader.readAsBinaryString(file);
		}
	}

	function readPoseFile (evt) {
		var files = evt.target.files;
		var file = files[0];
		var reader = new FileReader();
		reader.onload = function() {
			model.character.loadJSONPose(this.result);
		}
		reader.readAsText(file);
	}

	showSelectDialogBox = function(title, options, inputPlaceholder, onResult){
		swal({
		 title: title,
		 input: 'select',
		 inputOptions: options,
		 animation: false,
		 inputPlaceholder: inputPlaceholder,
		 showCancelButton: true,
		 inputValidator: function (value) {
		   return new Promise(function (resolve, reject) {
			 if (value !== '') {
			   resolve()
			 } else {
			   reject('Select a value.')
			 }
		   });
		}
		}).then(onResult);
	};

	showAttachBoneGroupDialog = function(onResult){
		options = {};
		attachPoints = model.getAvailableAttachPoints();
		for (var toBoneGroupUid in attachPoints){
			var toBoneGroupName = model.character.boneGroups.get(toBoneGroupUid).name;
			for (var i in attachPoints[toBoneGroupUid]){
				attachPoint = attachPoints[toBoneGroupUid][i];
				id = toBoneGroupUid + ';' + attachPoint;
				label = toBoneGroupName + ' (' + attachPoint.substring(1) + ')';
				options[id] = label;
			}
		}

		var parseResults = function(result){
			tokens = result.split(';');
			toBoneGroupUid = tokens[0];
			attachPoint = tokens[1];
			onResult(toBoneGroupUid, attachPoint);
		};

		showSelectDialogBox('Attach Bone Group',
							options,
							'Select Attach Point',
							parseResults);
	};

	clickedRemoveBoneGroup = function(boneGroupId){
		model.removeBoneGroup(boneGroupId);
	};


	setGlobalPose = function(){
		view.showLibrary('pose');
	};

	clickedAddBoneGroup = function(){
		view.selectBoneGroup(null);
		view.showLibrary('bone');
	};

	clickedMeshTab = function(){
		view.setMode('mesh');
	};

	clickedPoseTab = function(){
		view.setMode('pose');
	};

	clickedBoneGroupsTab = function(){
		view.setMode('bone');
	};

	clickedPresetsTab = function(){
		view.setMode('preset');
	};

	clickedCharacterTab = function(){
		view.setMode('character');
	};

	// Add mesh button
	$("#body-accordion").on("click",".mini-select[add-mesh-button]", function(e){
		var boneGroupUid = $(this).data("mesh-bone-group") + "";
		var boneGroup = model.character.boneGroups.get(boneGroupUid);
		model.addMesh(boneGroupUid, boneGroup.libraryName, "box");
		view.selectedBoneGroupUid = boneGroupUid;
		view.selectMeshFuture(view.selectedBoneGroupUid, "box");

		view.libraryClearMeshes();
		view.libraryPopulateMeshes(boneGroupUid);
		view.showLibrary('mesh');
	});

	// Click meshes tab mesh - select
	$("#body-accordion").on("click",".mini-select[meshes-tab-mesh]",function(e){
		var boneGroupUid = $(this).data("mesh-bone-group") + "";
		var meshName =  $(this).data("mesh-name");
		var mesh = model.character.boneGroups.get(boneGroupUid).meshes.get(meshName);
		view.selectedBoneGroupUid = boneGroupUid;
		view.selectMesh(mesh);
		view.libraryPopulateMeshes(boneGroupUid);
		view.showLibrary('mesh');
	});

	// Double-click meshes tab mesh - delete
	$("#body-accordion").on("dblclick",".mini-select[meshes-tab-mesh]", function(e){
		var boneGroupUid = $(this).data("mesh-bone-group") + "";
		var meshName =  $(this).data("mesh-name");
		view.hideLibrary('mesh');
		model.removeMesh(boneGroupUid, meshId);
	});

	// Click library mesh
	$("#mesh-library").on("click",".mini-select[data-mesh-id]", function(e){
		var mid = $(this).data("mesh-id");
		var library = $(this).data("mesh-library");
		var meshName = $(this).data("mesh-mesh-name");
		if (view.selectedMesh == null){ // Add mesh
			model.addMesh(view.selectedBoneGroupUid, library, meshName);
			view.selectMeshFuture(view.selectedBoneGroupUid, meshName);
		} else {
			var boneGroupUid = view.selectedMesh.boneGroupUid;
			model.removeMesh(view.selectedMesh.uid);
			model.addMesh(boneGroupUid, library, meshName);
			view.selectMeshFuture(boneGroupUid, meshName);
		}
	});

	// Click library pose
	$("#pose-library").on("click",".mini-select[data-pose-id]", function(e){
		var mid = $(this).data("pose-id");
		var library = $(this).data("pose-library");
		var poseName = $(this).data("pose-pose-name");
		model.loadJSONPose(library, poseName);
	});

	// Click library bone group
	$("#bone-library").on("click",".mini-select[data-bone-id]", function(e){
		var mid = $(this).data("bone-id");
		var library = $(this).data("bone-library");
		var boneGroupName = $(this).data("bone-bone-name").replaceAll('_', ' ');
		view.hideLibrary('bone');

		showAttachBoneGroupDialog(
			function(toBoneGroupUid, attachPoint){
				view.attachBoneGroupFuture(boneGroupName, toBoneGroupUid, attachPoint);
				view.addDefaultMeshFuture(boneGroupName);
				model.addBoneGroup(library, boneGroupName);
			});
		
	});

	//DO SOMETHING IF YOU CLICK A FILTER
	$("#mesh-library").on("click",".mini-select[data-tag-id]", function(e){

		//Tag ID of the filter is recorded on the element
		var tid = $(this).data("tag-id");
		alert("You clicked to filter on: " + tid );

		//Give it a nice UI decoration so you know you clicked it
		$(this).toggleClass("ui-selected");

		//GET ALL THE MODEL IDs WITH THAT TAG (if you've already done that call, this function will use the cached result)
		var url = "/api/v1/model/tags/" + tid;
		getModelsForTags(url,tid,function(){

			//now that we have the list, toggle UI elements without this tag
			//console.log(modelMap.tags[tid]);

			//find all mini-select items in the UI then filter them down to ones that are not in the list from the modelMap.tags data model 
			$(".mini-select[data-model-id]").filter( function(){
				//if it's -1, it's not in the list of approved models to show and it should have it's visibility toggled
				//remember the ids are stored as strings right now because they're properties not array indecies
				return modelMap.tags[tid].indexOf( $(this).data('model-id').toString() ) == -1;
				
			}).toggleClass("hidden");
		});
	});

	//DO SOMETHING IF YOU TYPE IN A FIELD
	//This just real time saves what you're typing into the object
	$("[data-bind]").on("keyup", function(){
		key = $(this).data("bind");
		value = $(this).val();
		figure[key] = value;
	});

	//HANDLE FILE UPLOADS
	var __file_key = "";  //need a global place to store the file name between async actions TODO: Make this better
	//data-folder="inspiration" data-upload="image" data-for="photo_inspiration"
	$("[data-upload]").fileupload({
		url: s3FormDetails.url,
		type: "POST",
		datatype: 'xml',
		add: function(e, data) {

			// Show warning message if your leaving the page during an upload.
			window.onbeforeunload = function () {
				return 'You have unsaved changes.';
			};

			// Give the file which is being uploaded it's current content-type (It doesn't retain it otherwise)
			// and give it a unique name (so it won't overwrite anything already on s3).
			var file = data.files[0];
			var folder = $(this).data('folder');

			__file_key = folder +"/"+ user.id + "." + Date.now() + '.' + file.name;

			data.formData = {
				"AWSAccessKeyId" : s3FormDetails.AWSAccessKeyId,
				"acl" : s3FormDetails.acl,
				"policy" : s3FormDetails.policy,
				"signature" : s3FormDetails.signature,
				'key' : __file_key,
				'Content-Type' : file.type,
				'success_action_redirect' : s3FormDetails.success_action_redirect
			};

			// Actually submit to form to S3.
			data.submit();

			// Show the progress bar
			// Uses the file size as a unique identifier
			var bar = $('<div class="progress" data-mod="'+file.size+'"><div class="bar"></div></div>');
			$('.progress-bar-area').append(bar);
			bar.slideDown('fast');

		},
		progress: function(e, data) {
			var percent = Math.round((data.loaded / data.total) * 100);
			$('.progress[data-mod="'+data.files[0].size+'"] .bar').css('width', percent + '%').html(percent+'%');
		},
		fail: function (e, data) {
			window.onbeforeunload = null;
			$('.progress[data-mod="'+data.files[0].size+'"] .bar').css('width', '100%').addClass('red').html('');

		},
		done: function (e, data) {
			window.onbeforeunload = null;

			// Upload Complete, show information about the upload in a textarea
			// from here you can do what you want as the file is on S3
			// e.g. save reference to your server using another ajax call or log it, etc.
			var original = data.files[0];
			var property = $(this).data('for');
			var type = $(this).data('upload');
			figure[property] = s3FormDetails.url + "/" + __file_key;
			figure.update();
			uilog(type + " Uploaded: " + original.name + " [" + original.size +"bytes]");
		}
	});

	
});

jQuery.get('/changelog.txt', function(data) {
	var arr = data.split('\n');
	var firstLine = arr[0];
	var theRest = arr.slice(1);
	/*sweetAlert({
		title: firstLine,
		html: theRest.join('<br>')
	});*/
});
</script>

<div id="body-accordion-container" class="col-md-12" oncontextmenu="return false;" style="position: absolute; margin-top: 40px; margin-left: 5px; top: 0%; left: 0%;">
	<div class="btn-group" data-toggle="buttons" id="mode-options" mesh="mode-options">
	  <label class="btn btn-primary nav-link mesh-btn active">
		<input type="radio" name="options" id="option1" autocomplete="off" checked onclick="clickedMeshTab();"> Appearance
	  </label>
	  <label class="btn btn-primary pose-btn">
		<input type="radio" name="options" id="option3" autocomplete="off" onclick="clickedPoseTab();"> Pose
	  </label>
	  <label class="btn btn-primary bone-btn">
		<input type="radio" name="options" id="option2" autocomplete="off" onclick="clickedBoneGroupsTab();">  Components
	  </label>
	  <label class="btn btn-primary preset-btn">
		<input type="radio" name="options" id="option4" autocomplete="off" onclick="clickedPresetsTab();"> Presets
	  </label>
	  <label class="btn btn-primary character-btn">
		<input type="radio" name="options" id="option5" autocomplete="off" onclick="clickedCharacterTab();"> Character
	  </label>
	</div>

<div id="body-accordion-container2" class="col-md-4" oncontextmenu="return false;" style="position: absolute; margin-top: 80px; margin-left: 5px; top: 0%; left: 0%;">

	<div class="mesh-info" id="mesh-help">
		<br>
		<label id="mesh-help-label">Right-click a mesh to select it.</label>
	</div>

	<div class="mesh-info" id="mesh-info">
		<br>
		<h3><label id="mesh-info-name">Mesh Name</label></h3>
		<label id="mesh-info-blurb">(Mesh info will go here.)</label>
		<br>
		<label id="mesh-info-author">Author: (author)</label>
		<br>
		<br>
		<label>Attached to:&nbsp;</label><label id="mesh-info-attached-to">stuff</label>
		<br>
		<br>

		<span class="btn-group">
			<button class="btn btn-primary" type="button" onclick="view.onDeletePressed()">Delete Mesh</button>
		</span>
		<br>
		<br>
		<span class="btn-group">
			<button class="btn btn-primary" type="button" onclick="view.clickedAddMesh()">Add Mesh</button>
		</span>
		<br>
		<br>
		<br>
		<label for="loadAsset" class="btn btn-secondary">Load Mesh</label>
		<input id="loadAsset" style="visibility:hidden;" type="file">
	</div>

	<div class="pose-info" id="pose-info">
		<br>
		<button class="btn btn-primary" type="button" onclick="model.saveCurrentPose();">Save Pose</button>
		<br>
		<br>
		<label for="loadPose" class="btn btn-primary">Load Pose</label>
		<input id="loadPose" style="visibility:hidden;" type="file">
	</div>

	<div class="bone-info" id="bone-help">
		<br>
		<label id="bone-help-label">Right-click a bone group to select it.</label>
	</div>

	<div class="bone-info" id="bone-info">
		<br>
		<h3><label id="bone-info-name">Bone Group Name</label></h3>
		<label id="bone-info-blurb">Here's a blurb about the bone group.</label>
		<br>
		<label id="bone-info-author">Author: stuff</label>
		<br>
		<br>
		<label>Attached to:&nbsp;</label><label id="bone-info-attached-to">stuff</label
		<br>
		<br>
		<span class="btn-group">
			<button class="btn btn-primary" type="button" onclick="showAttachBoneGroupDialog(function(toBoneGroupUid, attachPoint)
					{model.attachBoneGroup(view.selectedBoneGroup.uid, toBoneGroupUid, attachPoint);});">Attach to...</button>
		</span>
		<br>
		<br>
		<span class="btn-group">
			<button class="btn btn-primary" type="button" onclick="view.onDeletePressed()">Delete</button>
		</span>
		<br>
		<br>
		<span class="btn-group">
			<button class="btn btn-primary" type="button" onclick="clickedAddBoneGroup()">Add Bone Group</button>
		</span>
	</div>

	<div class="character-info" id="character-info">
		<br>
		<button class="btn btn-secondary" type="button" onclick="model.setResolution('low');">Low Res</button>
		<br>
		<button class="btn btn-secondary" type="button" onclick="model.setResolution('medium');">Medium Res</button>
		<br>
		<button class="btn btn-secondary" type="button" onclick="model.setResolution('high');">High Res</button>
		<br>
		<br>
		<button class="btn btn-primary" type="button" onclick="model.saveCharacter();">Save Character</button>
		<br>
		<br>
		<label for="loadCharacter" class="btn btn-primary">Load Character</label>
		<input id="loadCharacter" style="visibility:hidden;" type="file">
		<br>
		<br>
		<br>
		<span class="btn-group">
			<button class="btn btn-secondary" type="button" onclick="view.exportToSTL();">Export to .STL file</button>
		</span>
		<br>
	</div>

	<div class="preset-info" id="preset-info">
		<br>
		<span class="btn-group">
			<button class="btn btn-secondary" type="button" onclick="model.character.clear(); model.loadPreset('default', 'human male');">Human Male</button>
		</span>
		<br>
		<br>
		<span class="btn-group">
			<button class="btn btn-secondary" type="button" onclick="model.character.clear(); model.loadPreset('default', 'human female');">Human Female</button>
		</span>
		<br>
		<br>
		<label>Warning: Loading a preset will reset your character!</label>
	</div>
</div>




<div id="mesh-library" class="col-md-3" role="tablist" aria-multiselectable="true">
	<h2 class="text-white"> Mesh Library </h2>
</div>
<div id="pose-library" class="col-md-3" role="tablist" aria-multiselectable="true">
	<h2 class="text-white"> Pose Library </h2>
</div>
<div id="bone-library" class="col-md-3" role="tablist" aria-multiselectable="true">
	<h2 class="text-white"> Bone Group Library </h2>
</div>

<!-- <div id='status-box'>
	<h5 class='text-white float-left'>Console</h5>
	<div class="progress-bar-area float-right"></div>
	<form method="POST" enctype="multipart/form-data" class="direct-upload hidden">
	</form>
	<div>
		<textarea id="uiconsole"></textarea>
	</div>
</div>-->

<div id='loadingDiv'>
	<img src='/img/loading.gif'>
</div>

<!--Feature Specific Scripts (be sure they load after the js in the footer with document.ready-->
<script src="/vendor/threejs/build/three.js"></script>
<script src="/vendor/threejs/external/OrbitControls.js"></script>
<script src="/vendor/threejs/external/STLExporter.js"></script>
<script src="/vendor/threejs/examples/js/loaders/STLLoader.js"></script>

<script src="/js/bootstrap/tab.js"></script>

<script src="/js/Editor.js"></script>
<script src="/js/UserSettings.js"></script>
<script src="/js/LocalDataSource.js"></script>
<script src="/js/SceneModel.js"></script>
<script src="/js/SceneView.js"></script>
<script src="/js/Character.js"></script>
<script src="/js/BoneGroup.js"></script>
<script src="/js/Pose.js"></script>
<script src="/js/Materials.js"></script>
<script src="/js/PickingView.js"></script>

<script src="/js/Event.js"></script>
<script src="/js/ObservableDict.js"></script>
<script src="/js/ObservableList.js"></script>

<script src="/js/FileSaver.js"></script>
<script src="/js/Global.js"></script>

<!-- Load the FileUpload Plugin (more info @ https://github.com/blueimp/jQuery-File-Upload) -->
<script src="/vendor/jqueryui/1.11.4/jquery-ui.min.js"></script>
<script src="/vendor/blueimp-file-upload/9.5.7/jquery.fileupload.js"></script>

<script src="/js/sweetalert2.min.js"></script>
<link rel="stylesheet" href="/css/sweetalert2.min.css">
