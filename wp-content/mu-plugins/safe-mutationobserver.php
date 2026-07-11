<?php
/*
Plugin Name: Safe MutationObserver Guard
Description: Prevents frontend JS errors when MutationObserver.observe() receives a non-Node target.
*/
add_action('wp_head', function () {
    echo "<script>(function(){if(!window.MutationObserver||MutationObserver.prototype.__safeObservePatched)return;MutationObserver.prototype.__safeObservePatched=true;var original=MutationObserver.prototype.observe;MutationObserver.prototype.observe=function(target,options){if(!(target instanceof Node)){return;}return original.call(this,target,options);};})();</script>
";
}, 1);
