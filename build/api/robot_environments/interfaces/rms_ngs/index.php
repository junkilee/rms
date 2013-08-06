<?php
/**
 * A demo interface for National Geographic for use within RMS.
 *
 * @author     Russell Toris <rctoris@wpi.edu>
 * @copyright  2013 Worcester Polytechnic Institute
 * @license    BSD -- see LICENSE file
 * @version    June, 3 2013
 * @link       http://ros.org/wiki/rms_interactive_world
 */

/**
 * A static class to contain the interface generate function.
 *
 * @author     Russell Toris <rctoris@wpi.edu>
 * @copyright  2013 Worcester Polytechnic Institute
 * @license    BSD -- see LICENSE file
 * @version    June, 3 2013
 */
class rms_ngs
{
    /**
     * Generate the HTML for the interface. All HTML is echoed.
     * @param robot_environment $re The associated robot_environment object for
     *     this interface
     */
    function generate($re)
    {
        global $title;
        
        // check if we have enough valid widgets
        if (!$streams = $re->get_widgets_by_name('MJPEG Stream')) {
            robot_environments::create_error_page(
                'No MJPEG streams found.',
                $re->get_user_account()
            );
        } else if (!$teleop = $re->get_widgets_by_name('Keyboard Teleop')) {
            robot_environments::create_error_page(
                'No Keyboard Teloperation settings found.',
                $re->get_user_account()
            );
        } else if (!$im = $re->get_widgets_by_name('Interactive Markers')) {
            robot_environments::create_error_page(
                'No Interactive Marker settings found.',
                $re->get_user_account()
            );
        } else if (!$nav = $re->get_widgets_by_name('2D Navigation')) {
            robot_environments::create_error_page(
                'No 2D Navaigation settings found.',
                $re->get_user_account()
            );
        } else if (!$re->authorized()) {
            robot_environments::create_error_page(
                'Invalid experiment for the current user.',
                $re->get_user_account()
            );
        } else {
            // lets create a string array of MJPEG streams
            $topics = '[';
            $labels = '[';
            foreach ($streams as $s) {
                $topics .= "'".$s['topic']."', ";
                $labels .= "'".$s['label']."', ";
            }
            $topics = substr($topics, 0, strlen($topics) - 2).']';
            $labels = substr($labels, 0, strlen($topics) - 2).']';

            // we will also need the map
            $widget = widgets::get_widget_by_table('maps');
            $map = widgets::get_widget_instance_by_widgetid_and_id(
                $widget['widgetid'], $nav[0]['mapid']
            );

            $collada = 'ColladaAnimationCompress/0.0.1/ColladaLoader2.min.js'?>
<!DOCTYPE html>
<html>
<head>
<?php $re->create_head() // grab the header information ?>
<title><?php echo $title?></title>
<script type="text/javascript"
  src="http://cdn.robotwebtools.org/threejs/r56/three.min.js">
</script>
<script type="text/javascript"
  src="http://cdn.robotwebtools.org/EventEmitter2/0.4.11/eventemitter2.js">
</script>
<script type="text/javascript"
  src="http://cdn.robotwebtools.org/<?php echo $collada?>">
</script>
<script type="text/javascript"
  src="http://cdn.robotwebtools.org/roslibjs/r5/roslib.js"></script>
<script type="text/javascript"
  src="http://cdn.robotwebtools.org/mjpegcanvasjs/r1/mjpegcanvas.min.js">
</script>
<script type="text/javascript"
  src="http://cdn.robotwebtools.org/keyboardteleopjs/r1/keyboardteleop.min.js">
</script>
<script type="text/javascript"
  src="http://cdn.robotwebtools.org/ros3djs/r6/ros3d.min.js">
</script>
<script type="text/javascript"
  src="http://cdn.robotwebtools.org/EaselJS/0.6.0/easeljs.min.js">
</script>
<script type="text/javascript"
  src="http://cdn.robotwebtools.org/ros2djs/r2/ros2d.min.js">
</script>
<script type="text/javascript"
  src="http://cdn.robotwebtools.org/nav2djs/r1/nav2d.min.js">
</script>
  

<script type="text/javascript">
  //connect to ROS
  var ros = new ROSLIB.Ros({
    url : '<?php echo $re->rosbridge_url()?>'
  });

  ros.on('error', function() {
	writeToTerminal('Connection failed!');
  });

  /**
   * Write the given text to the terminal.
   *
   * @param text - the text to add
   */
  function writeToTerminal(text) {
    var div = $('#terminal');
    div.append('<strong> &gt; '+ text + '</strong><br />');
    div.animate({
      scrollTop : div.prop("scrollHeight") - div.height()
    }, 50);
  }

  /**
   * Load everything on start.
   */
  function start() {
    // create MJPEG streams
    new MJPEGCANVAS.MultiStreamViewer({
      divID : 'video',
      host : '<?php echo $re->get_mjpeg()?>',
      port : '<?php echo $re->get_mjpegport()?>',
      width : 400,
      height : 300,
      topics : <?php echo $topics?>,
      labels : <?php echo $labels?>
    });

    // initialize the teleop
    new KEYBOARDTELEOP.Teleop({
      ros : ros,
      topic : '<?php echo $teleop[0]['twist']?>',
      throttle : '<?php echo $teleop[0]['throttle']?>'
    });

    // create the main viewer
    var viewer = new ROS3D.Viewer({
      divID : 'scene',
      width :  $(document).width(),
      height : $(document).height(),
      antialias : true
    });
    viewer.addObject(new ROS3D.Grid());

    // setup a client to listen to TFs
    var tfClient = new ROSLIB.TFClient({
      ros : ros,
      angularThres : 0.01,
      transThres : 0.01,
      rate : 10.0,
      fixedFrame : '<?php echo $im[0]['fixed_frame'] ?>'
    });
    
    new ROS3D.OccupancyGridClient({
      ros : ros,
      rootObject : viewer.scene,
      topic : '<?php echo $map['topic']?>',
      tfClient : tfClient
    });

    // setup the URDF client
    new ROS3D.UrdfClient({
      ros : ros,
      tfClient : tfClient,
      path : 'http://resources.robotwebtools.org/',
      rootObject : viewer.scene
    });

    // setup the marker clients
    <?php
    foreach ($im as $cur) {?>
      new ROS3D.InteractiveMarkerClient({
        ros : ros,
        tfClient : tfClient,
        topic : '<?php echo $cur['topic'] ?>',
        camera : viewer.camera,
        rootObject : viewer.selectableObjects,
        path : 'http://resources.robotwebtools.org/'
      });
    <?php 
    }
    ?>

    // 2D viewer
    var navView = new ROS2D.Viewer({
      divID : 'nav',
      width : 400,
      height : 300
    });
    NAV2D.OccupancyGridClientNav({
      ros : ros,
      rootObject : navView.scene,
      viewer : navView,
      serverName : '<?php echo $nav[0]['actionserver']?>',
      actionName : '<?php echo $nav[0]['action']?>',
      topic : '<?php echo $map['topic']?>',
      continuous : <?php echo ($map['continuous'] === 0) ? 'true' : 'false'?>
    });

    // keep the camera centered at the head
    tfClient.subscribe('/head_mount_kinect_rgb_link', function(tf) {
      viewer.cameraControls.center.x = tf.translation.x;
      viewer.cameraControls.center.y = tf.translation.y;
      viewer.cameraControls.center.z = tf.translation.z;
    });

    // move the overlays
    $('#nav').css({left:($(document).width()-400)+'px'});
    $('#toolbar').css({width:($(document).width()-800)+'px'});
    $('#terminal').css({top:($(document).height()
        -$('#terminal').height())+'px'});

    $(':button').button();
    $(':button').css('font-size', '20px');

    // create the segment button
     var segment = new ROSLIB.ActionClient({
      ros : ros,
      serverName : '/object_detection_user_command',
      actionName : 'pr2_interactive_object_detection/UserCommandAction'
    });
    $('#segment').button().click(function() {
      var goal = new ROSLIB.Goal({
        actionClient : segment,
        goalMessage : {
          request : 1,
          interactive : false
        }
      });
      goal.send();
      writeToTerminal('Object Detection: Segmenting image');
    });
    
    // create the align button
     var hla = new ROSLIB.ActionClient({
      ros : ros,
      serverName : '/high_level_actions',
      actionName : 'higher_level_actions/HighLevelAction'
    });
    $('#align').button().click(function() {
      var goal = new ROSLIB.Goal({
        actionClient : hla,
        goalMessage : {
          actionType : 'alignTable'
        }
      });
      goal.send();
    });
    
    // setup a client to listen to table proximity
    var tableProximityClient = new ROSLIB.Topic({
      ros : ros,
      name : '/high_level_actions/nearTable',
      messageType : '/higher_level_actions/NearTable'
    });
    // enable or disable the align button depending on table proximity
    tableProximityClient.subscribe(function(msg) {
      if (msg.isNearTable)
      {
        $('#align').button('enable');
      }
      else
      {
        $('#align').button('disable');
      }
    });
    
    // setup a client to listen to align feedback
    var hlaFeedback = new ROSLIB.Topic({
      ros : ros,
      name : '/high_level_actions/feedback',
      messageType : '/higher_level_actions/HighLevelActionFeedback'
    });
    // write status updates to the terminal
    hlaFeedback.subscribe(function(msg) {
      writeToTerminal('Table Alignment: ' + msg.feedback.currentStep);
    });
    
    // setup a client to listen to segmentation results
    var segmentationFeedback = new ROSLIB.Topic({
      ros : ros,
      name : '/object_detection_user_command/result',
      messageType : '/pr2_interactive_object_detection/UserCommandActionResult'
    });
    // write status updates to the terminal
    segmentationFeedback.subscribe(function(msg) {
      writeToTerminal('Object Detection: ' + msg.status.text);
      writeToTerminal('Object Detection: Action finished');
    });
    
    // setup a client to listen to navigation goals
    var navGoalFeedback = new ROSLIB.Topic({
      ros : ros,
      name : '/move_base/goal',
      messageType : '/move_base_msgs/MoveBaseActionGoal'
    });
    // write status updates to the terminal
    navGoalFeedback.subscribe(function(msg) {
      writeToTerminal('Navigation: New goal received (' 
        + msg.goal.target_pose.pose.position.x + ',' 
        + msg.goal.target_pose.pose.position.y + ')');
    });
    
    // setup a client to listen to navigation results
    var navFeedback = new ROSLIB.Topic({
      ros : ros,
      name : '/move_base/result',
      messageType : '/move_base_msgs/MoveBaseActionResult'
    });
    // write status updates to the terminal
    navFeedback.subscribe(function(msg) {
      writeToTerminal('Navigation: ' + msg.status.text);
      writeToTerminal('Navigation: Action finished');
    });
    
    // setup a client to listen to pickup goals
    var navGoalFeedback = new ROSLIB.Topic({
      ros : ros,
      name : '/object_manipulator/object_manipulator_pickup/goal',
      messageType : '/object_manipulation_msgs/PickupActionGoal'
    });
    // write status updates to the terminal
    navGoalFeedback.subscribe(function(msg) {
      writeToTerminal('Pickup: New goal received');
    });
    
    // setup a client to listen to pickup results
    var pickupFeedback = new ROSLIB.Topic({
      ros : ros,
      name : '/object_manipulator/object_manipulator_pickup/result',
      messageType : 'object_manipulation_msgs/PickupActionResult'
    });
    // write status updates to the terminal
    pickupFeedback.subscribe(function(msg) {
      if (msg.result.attempted_grasp_results.length > 0 
        && msg.result.attempted_grasp_results[
        msg.result.attempted_grasp_results.length - 1].result_code === 1)
      {
        writeToTerminal('Pickup: Succeeded');
      }
      else
      {
        writeToTerminal('Pickup: Failed');
      }
      writeToTerminal('Navigation: Action finished');
    });

    // fixes the menu in the floating camera feed
    $('body').bind('DOMSubtreeModified', function() {
    	$('body div:last-child').css('z-index', 750);
    });

    writeToTerminal('Interface initialization complete.');
  }
</script>
</head>
<body onload="start();">
  <div class="mjpeg-widget" id="video"></div>
  <div class="nav-widget" id="nav"></div>
  <div class="toolbar" id="toolbar">
    <img src="../api/robot_environments/interfaces/rms_ngs/img/wpi.png" />
    &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
    <button id="align">Align and Detect</button>
    &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
    <button id="segment">Re-Detect</button>
    &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
    <img src="../api/robot_environments/interfaces/rms_ngs/img/brown.png" />
  </div>
  <div id="terminal" class="terminal"></div>
  <div id="scene" class="scene"></div>
</body>
</html>
<?php
        }
    }
}
