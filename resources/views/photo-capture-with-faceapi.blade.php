<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Capture Photo</title>
    <script src="/js-library/face-api.min.js"></script>
    <script src="/js-library/jquery-3.7.1.min.js"></script>
    <script src="/js-library/qrcode.min.js"></script>
    <style>
        #canvas {
            position: absolute;
            top: 0;
            left: 0;
            z-index: 2;
            /* Set a higher z-index to ensure it appears on top of the video */
        }

        body {
            background: #555;
        }

        .content {
            max-width: 500px;
            margin: auto;
            background: white;
            padding: 10px;
            position: relative;
            /* Add position: relative to the parent to make absolute positioning work */
        }

        #loadingIndicator {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 100%;
            height: 100%;
            position: absolute;
            top: 0;
            left: 0;
            background-color: rgba(255, 255, 255, 0.8);
            /* Semi-transparent white background */
            z-index: 3;
            /* Set a higher z-index to ensure it appears on top of video and canvas */
        }
    </style>
</head>

<body class="content">

    <div id="cameraBlock">
        <div id="loadingIndicator">Loading...</div>
        <video id="video" width="{{ $width }}" height="{{ $height }}" autoplay></video>
        <canvas id="canvas" width="{{ $width }}" height="{{ $height }}"></canvas>
    </div>

    <div id="noCameraBlock" style="display: none;">
        <p>কোন ক্যামেরে পাওয়া যায়নি</p>
        <p>ক্যামেরে আছে এমন কোন ডিভাইস থেকে QR-কোডটি স্ক্যান করুন বা লিঙ্কটি কপি করে ব্রাউজ করুন। </p>
        <div id="qrcode"></div>
        <div>
            <input type="text" id="urlInput" value="{{ route('capture') }}" readonly />
            <br>
            <button id="copyButton">Copy URL</button>
        </div>
    </div>

    <form id="imageForm" method="POST" action="{{ route('capture') }}">
        @csrf
        <input type="hidden" id="capturedImageData" name="capturedImageData" />
        <div id="imageContainer"></div>
    </form>

    <!-- Initially hide the buttons -->
    <button id="saveButton" style="display: none;">Save</button>
    <button id="retakeButton" style="display: none;">Retake</button>

    <div id='instructions'>
        <ul>
            <li>আপনার মুখমণ্ডল ফ্রেমের মাঝখানে নিয়ে আসুন ।</li>
            <li>ছবি তোলার জন্য উচ্চারণ করুন (শব্দ করে): বাংলাদেশ ।</li>
        </ul>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', async () => {

            // photo capture conditions
            let mouthOpenWasDetected = false;
            let mouthIsClosed = false;
            let isCenterPostion = false;
            let isCaptured = false;
            let loadingIndicatorHidden = false;

            // captured Image
            let capturedImage = null;

            // Load the face-api.js models
            await faceapi.nets.tinyFaceDetector.loadFromUri('/models');
            await faceapi.nets.faceLandmark68Net.loadFromUri('/models');
            // await faceapi.nets.faceLandmark68TinyNet.loadFromUri('/models');

            // Get access to the webcam
            const video = document.getElementById('video');
            const canvas = document.getElementById('canvas');
            const context = canvas.getContext('2d');

            try {
                const stream = await navigator.mediaDevices.getUserMedia({
                    video: {}
                });
                video.srcObject = stream;

                // Wait for the video to play and then start face detection
                video.addEventListener('play', () => {

                    if (!loadingIndicatorHidden) {
                        loadingIndicatorHidden = true;
                        document.getElementById('loadingIndicator').style.display = 'none';
                    }

                    // Get video dimensions
                    const videoSize = {
                        width: video.width,
                        height: video.height
                    };

                    // Resize the canvas to match the video dimensions
                    faceapi.matchDimensions(canvas, videoSize);

                    setInterval(async () => {
                        // Detect faces and landmarks
                        const detections = await faceapi.detectAllFaces(video, new faceapi
                                .TinyFaceDetectorOptions())
                            .withFaceLandmarks();
                        // .withFaceLandmarks(true);

                        // Clear the canvas before draw face and eye
                        context.clearRect(0, 0, canvas.width, canvas.height);

                        // Draw face and eye rectangles
                        faceapi.draw.drawDetections(canvas, detections);
                        faceapi.draw.drawFaceLandmarks(canvas, detections);

                        if (detections.length > 0) {

                            if (detections.length > 1) {
                                // If it's necessary, display an error message.
                                // Multiple Face Detected. Kindly ensure that only a single face is detected.
                            }

                            const landmarks = detections[0].landmarks;

                            // const jawOutline = landmarks.getJawOutline()
                            // const nose = landmarks.getNose()
                            // const mouth = landmarks.getMouth()
                            // const leftEye = landmarks.getLeftEye()
                            // const rightEye = landmarks.getRightEye()
                            // const leftEyeBbrow = landmarks.getLeftEyeBrow()
                            // const rightEyeBrow = landmarks.getRightEyeBrow()

                            const mouthPoints = landmarks.getMouth();

                            // Log mouthPoints in JSON format
                            // Points 0-7: Represent the upper lip, (x: left->right, y: uppper->lower)
                            // Points 8-11: Represent the lower lip, (x: right->left, y: uppper->lower)
                            // Points 0-11 : Lips (x: clock wise, y: uppper->lower)
                            // Points 12-19: Represent the inner part of the mouth, including points around the mouth opening (x: clock wise, y: uppper->lower)
                            // console.log('Mouth Landmarks:', JSON.stringify(mouthPoints));

                            const mouthTop = mouthPoints[14].y;
                            const mouthBottom = mouthPoints[18].y;
                            const mouthHeight = mouthBottom - mouthTop;

                            // you can adjust this value (greater for tiny model)
                            const mouthOpenThreshold = 7;
                            const isMouthOpen = mouthHeight > mouthOpenThreshold;

                            // Detect open mouth
                            if (isMouthOpen) {
                                mouthOpenWasDetected = true;
                                // console.log('Mouth Open Detected');
                            } else {
                                // console.log('Mouth close Detected');
                            }

                            if (mouthOpenWasDetected) {
                                if (!isMouthOpen) {
                                    mouthIsClosed = true;
                                }
                            }

                            let box = detections[0].detection.box;
                            // console.log('Box: ', JSON.stringify(box));

                            let xPosition = true;
                            let yPosition = true;

                            // if ((box.x + box.width) < video.width && box.x > 50) {
                            //     xPosition = true;
                            // }

                            // if ((box.y + box.height) < (video.height - (box.height / 1)) &&
                            //     box.height > (box.height / 3)) {
                            //     yPosition = true;
                            // }

                            // isCenterPostion
                            if (xPosition && yPosition) {
                                isCenterPostion = true;
                            }

                            if (mouthOpenWasDetected && mouthIsClosed && !isCaptured &&
                                isCenterPostion) {
                                setTimeout(() => {
                                    captureImage(box);
                                }, 2000); // 2000 milliseconds (2 seconds)
                            }
                        }
                    }, 100); // Adjust the interval based on your needs
                });
            } catch (error) {
                console.error('Error accessing the webcam : ', error);

                // Device does not have a camera
                $('#instructions').hide();
                $('#cameraBlock').hide();
                $('#noCameraBlock').show();

                // Generate and display QR code
                const url = $('#urlInput').val();
                new QRCode(document.getElementById("qrcode"), url);

                // Copy URL to clipboard
                $('#copyButton').on('click', function() {
                    const urlInput = document.getElementById('urlInput');
                    urlInput.select();
                    document.execCommand('copy');
                    alert('URL copied to clipboard!');
                });

            }

            function captureImage(box) {
                if (!isCaptured) {
                    isCaptured = true;

                    let xPadding = 10;
                    let yPadding = 30;

                    let dx = box.x - xPadding;
                    let dy = box.y - yPadding * 3;
                    let dWidth = box.width + (xPadding * 2);
                    let dHeight = box.height + (yPadding * 3);

                    // Create a new canvas to crop the drawn region
                    const cropCanvas = document.createElement('canvas');
                    cropCanvas.width = dWidth;
                    cropCanvas.height = dHeight;
                    const cropContext = cropCanvas.getContext('2d');
                    cropContext.drawImage(video, dx, dy, dWidth, dHeight, 0, 0, dWidth, dHeight);

                    // Get the image data from the cropped canvas as a data URL
                    var imageSrc = cropCanvas.toDataURL('image/png');

                    capturedImage = imageSrc;

                    // cleare drawing
                    context.clearRect(0, 0, canvas.width, canvas.height);

                    // stop video
                    stopVideo();

                    // Display the captured image inside the imageContainer
                    $('#imageContainer').html('<img src="' + imageSrc +
                        '" alt="Captured Image" />');

                    // Update the value of the hidden input field
                    $('#capturedImageData').val(imageSrc);

                    // Show the "Save" and "Retake" buttons
                    $('#saveButton, #retakeButton').show();

                    // Hide the video and canvas
                    $('#video, #canvas').hide();

                }
            }

            const stopVideo = () => {
                const stream = video.srcObject;
                if (stream) {
                    const tracks = stream.getTracks();
                    tracks.forEach((track) => {
                        track.stop();
                    });
                    video.srcObject = null;
                }
            };
        });

        // Save button click event
        $('#saveButton').on('click', function() {
            // Submit the form
            $('#imageForm').submit();
        });

        // Retake button click event
        $('#retakeButton').on('click', function() {
            // Reset the capture state
            resetCapture();
            location.reload();
        });

        function resetCapture() {
            isCaptured = false;
            capturedImage = '';
            // Show video and hide image container
            $('#video, #canvas').show();
            $('#imageContainer').hide();
        }
    </script>

</body>

</html>
