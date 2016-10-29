function Pose(){
	this.poseBones = [];
}

Pose.toJson = function(boneGroups){
	var pose = Pose.toPose(boneGroups);
	return JSON.stringify(pose, ' ', ' ');
}

Pose.toPose = function(boneGroups){
	var pose = new Pose();

	for (var boneGroupName in boneGroups.dict){

		var boneGroup = boneGroups.get(boneGroupName);
		for (var i = 0; i < boneGroup.skeleton.bones.length; i++){
			var bone = boneGroup.skeleton.bones[i];
			if (bone.name.startsWith("#")){ // Ignore attach points
				continue;
			}

			poseBone = new PoseBone(bone.name, bone.position, bone.rotation, bone.scale);
			pose.poseBones.push(poseBone);
		}
	}

	return pose;
}

Pose.fromJson = function(jsonString){
	return JSON.parse(jsonString);
}

PoseBone = function(name, position, rotation, scale){
	this.name = name;
	this.position = new THREE.Vector3(position.x, position.y, position.z);
	this.rotation = new THREE.Vector3(rotation.x, rotation.y, rotation.z);
	this.scale = new THREE.Vector3(scale.x, scale.y, scale.z);
}