(function($) {
    var cdn = simple_s3_upload.cdn_url;

	var helpers = {
        mediaPage: function() {
            return $("#video_upload-video-box").hasClass('media-item');
        },
        make_thumb_url: function(video_key, size) {
            var thumb = '-00001';

            if (size === 'small') {
                thumb += '-small.jpg';
            } else {
                thumb += '.png';
            }

            return cdn + video_key.replace('converted', 'thumbnails').replace('.mp4', thumb);
        },
        insert_quicktag: function(video_key) {
            var quicktag = '[video_upload file="' + cdn + video_key + '" image="' + helpers.make_thumb_url(video_key) + '"]';
            if (helpers.mediaPage) {
                parent.send_to_editor(quicktag);
            } else {
                window.send_to_editor(quicktag);
            }
            return false;
        }
    };

    $(function() {
        AWS.config.update({accessKeyId: simple_s3_upload.aws_public_key, secretAccessKey: simple_s3_upload.aws_secret_key});
        AWS.config.region = simple_s3_upload.aws_region;

        var s3 = new AWS.S3({
	        params: {
	        	Bucket: simple_s3_upload.s3_bucket
	        }
        });

        // Load uploaded videos to choose from
        s3.listObjects({
	        Delimiter: ',',
	        EncodingType: 'url',
	        MaxKeys: 1000,
	        Prefix: simple_s3_upload.file_prefix + '/converted'
        },
        function(err, data) {
            if (err) {
                console.log(err, err.stack); // an error occurred

            } else {
                //Sort videos by date descending
                data.Contents.sort(function(a,b) { 
                    return new Date(b.LastModified).getTime() - new Date(a.LastModified).getTime() 
                });

                $.each(data.Contents, function(k, v) {
                    if (v.Key.indexOf('-small') === -1) {
                        var thumb_url = helpers.make_thumb_url(v.Key, 'small');

                        var css_class = k % 2 ? 'video_upload-odd' : 'video_upload-even';

                        var make_quicktag = function(video_key) {
                            return function() {
                                helpers.insert_quicktag(video_key);
                            }
                        }(v.Key);

                        // Create the list item
                        var elt = $('<li>').attr('id', 'video-' + k);
                        elt.addClass(css_class);

                        if (make_quicktag) {
                            // If we can embed, add the functionality to the item
                            elt.click(make_quicktag);
                        }

                        $('<img src="' + thumb_url + '">').appendTo(elt);
                        $('<p>').text(v.Key.split("/").pop()).appendTo(elt);

                        $('#video_upload-video-list').append(elt);
                    }
                });
            }
        });

    
        $('#fine-uploader-s3').fineUploaderS3({
            template: 'qq-template-s3',
            objectProperties: {
                acl: 'public-read',
                key: function (fileId) {
                    var $this = $('#fine-uploader-s3');
                    var filename = $this.fineUploader('getName', fileId).toLowerCase().replace(/\s/g, '-');

                    return  simple_s3_upload.file_prefix + '/raw/' + filename;
                }
            },
            request: {
                endpoint: "https://s3.amazonaws.com/" + simple_s3_upload.s3_bucket,
                accessKey: simple_s3_upload.aws_public_key
            },
            signature: {
                endpoint: simple_s3_upload.ajax_url + "?action=simple_s3_upload_cors"
            },
            uploadSuccess: {
                endpoint: simple_s3_upload.ajax_url + "?action=simple_s3_upload_cors&success",
            },
            callbacks: {
                onComplete: function(id, name, response) {
                    //console.log('Done');
                }
            }
        });
   
    });
})(jQuery);