<div id="Top Checks" class="topChecks">

    <div id="outerSplitContain" class="checks checkPad">
        Split&nbsp;&nbsp;<input type="checkbox" name="split" id="splitLoad" value="split" onClick="addSplit('splitLoad', 'deliveryLocation', 'split')" />&nbsp;&nbsp;&nbsp;&nbsp;
    </div><!--end outerSplitContain-->
    
    <div id="outerBeginContain" class="checks checkPad">
        Begin Empty&nbsp;&nbsp;<input type="checkbox" name="begin" id="be" value="begin" onClick="addDeadhead('be', 'beginEmpty')" />
    </div><!--end outerBeginContain-->
    
    <div id="outerFiveContain" class="checks">
        Extra&nbsp;&nbsp;<input type="checkbox" id="extra" name="extra" value="extra" onClick="addExtra('extra', 'extraBox')"  />
        <div id="extraBox" class="extraBox"></div>
    </div><!--end outerFiveContain-->

    <div id="outerWeekendContain" class="checks">
        Weekend&nbsp;&nbsp;<input type="checkbox" name="weekend" value="weekend" <?php echo $ws; ?>  />
    </div><!--end outerWeekendContain-->
</div><!--end Top Checks-->
<div class="clear"></div>
