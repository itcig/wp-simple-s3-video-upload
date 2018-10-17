# Simple S3 Upload #
S3 video upload, transcode and embed for Wordpress

## Plugin Installation

1. Install Wordpress plugin as normal

1. Add values to *S3 Video Upload* settings page:

 **S3 Bucket** - The bucket name to upload

 **S3 Region** - The region your bucket belongs to e.g. us-east-1

 **Public Key** - The public key credential for your IAM user

 **Secret Key** - The secret access key for your IAM user

 **S3 File Prefix** - The key prefix (folder) inside the bucket to store uploads, converted videos and thumbnails e.g. 'videos'

 **CDN Url** - The fully qualified Url for a CDN if masking your S3 Url

1. You can upload videos when editing a Post via the *Add Media* button and then click *Upload Video* on the left. Videos will apepar in the list of uploads after they are done transcoding.

1. Add video to content by clicking on it from *Add Media* > *Video Uploads* page and it will insert a shortcode into the content.

## Amazon Setup

We will be using multiple AWS services to automate the transcoding: S3, Lambda, Elastic Transcoder, Simple Notification Service

1. Create an IAM user that has read and write access to S3

1. Create a new *Pipeline* in **Elastic Transcoder** 
	- Give it any name
	- Add your bucket in all relevant *bucket* inputs
	- You can use the default Elastic Transcoder role or create one with access to Transcoder
	- For both Transcoded Files and Thumbnail sections:
		- Select *Standard* for both *Storage Class*
		- Use any grantee you wish, I use *Amazon S3 Group* and pick a group with write access to S3 
	- (optional) Add any notifications you want for *On Completion Event* or *On Error Event* to notify of job completion/failure via SNS. To use this you must create or use an exiting SNS *Topic*

1. Create *Presets* in **Elastic Transcoder**. There are a ton of options for video/thumbanil size, quality and more. Ideally create 2 presets for HD and SD depending on your needs and then add the Preset Id to the Lambda script below.  

1. Create a new **Lambda Function** using Node.js and the default service-role/Lambda and copy the below code. YOU MUST REPLACE ALL THE SETTINGS VALUES at the top of the script:
 
	```javascript
	'use strict';
	
	var settings = {
		bucket: 'REPLACE_ME', //Full bucket name
		pipelineId: 'REPLACE_ME', //pipeline Id
		presets: {
			hd: 'REPLACE_ME', //preset Id
			sm: 'REPLACE_ME', //preset Id
		},
		//Paths should end with a trailing slash
		paths: { 
			uploads: 'REPLACE_ME', //e.g. 'videos/converted/'
			thumbnails: 'REPLACE_ME', //e.g. 'videos/thumbnails/'
		}
	};
	
	var AWS = require('aws-sdk');
	
	var s3 = new AWS.S3({
	 apiVersion: '2012–09–25'
	});
	
	var eltr = new AWS.ElasticTranscoder({
	 apiVersion: '2012–09–25',
	 region: 'us-east-1'
	});
	
	var timestamp = Math.floor(Date.now() / 1000);
	
	exports.handler = function(event, context) {
	 
	 console.log('Executing Elastic Transcoder Orchestrator');
	 
	 var bucket = event.Records[0].s3.bucket.name;
	 
	 var key = event.Records[0].s3.object.key;
	 
	 var pipelineId = settings.pipelineId;
	 if (bucket !== settings.bucket) {
	  context.fail('Incorrect Video Input Bucket');
	  return;
	 }
	 
	 var srcKey =  decodeURIComponent(event.Records[0].s3.object.key.replace(/\+/g, " ")); //the object may have spaces  
	 
	 var newKey = key.split("/").pop().split('.')[0] + '-' + timestamp;
	 
	 var params = {
	  PipelineId: pipelineId,
	  Input: {
	   Key: srcKey,
	   FrameRate: 'auto',
	   Resolution: 'auto',
	   AspectRatio: 'auto',
	   Interlaced: 'auto',
	   Container: 'auto'
	  },
	  Outputs: [{
	   Key: settings.paths.converted + newKey + '.mp4',
	   ThumbnailPattern: settings.paths.thumbnails + newKey + '-{count}',
	   PresetId: settings.presets.hd,
	  },{
	   Key: settings.paths.converted + newKey + '-small.mp4',
	   ThumbnailPattern: settings.paths.thumbnails + newKey + '-{count}-small',
	   PresetId: settings.presets.sm,
	  }]
	 };
	 
	 console.log('Starting Job');
	 
	 eltr.createJob(params, function(err, data){
	  if (err){
	   console.log(err);
	  } else {
	   console.log(data);
	  }
	  context.succeed('Job well done');
	 });
	};
	```

	
1. On the bucket *Properties* > *Events* add a new *Notification*.
	- Pick a name for the job like 'Transcode Videos'
	- Event: ObjectCreate (All)
	- Prefix: {folder}/raw (raw is the subfolder in the folder of your choice where uploads will go automatically
	- Send to: Lambda Function
	- Lambda: 'video-transcode'
