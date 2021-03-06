<?php

class RatingGuide
{
	/**
	 * Contains the rating guide
	 *
	 * @var string
	 */
	protected $guide = '
	    <table class="table table-striped table-hover">
	      <thead>
	        <tr>
	          <th>Rating</th>
	          <th>Level</th>
	          <th>Experience</th>
	          <th>Problem Solving</th>
	        </tr>
	      </thead>
	      <tbody>
	        <tr>
	          <td>1</td>
	          <td>New or Inexperienced</td>
	          <td>I might know how to do shifts and most responsibilities. I haven’t had many shifts or my last shift is some time ago.</td>
	          <td><b>None up to a few</b> problems (e.g. restarting DAQ).</td>
	        </tr>
	        <tr>
	          <td>2</td>
	          <td>Basic Shift Knowledge</td>
	          <td>I can perform all shift responsibilities including TaggEff etc. I know what the online spectra mean. But I’m not confident enough to do everything alone.</td>
	          <td>I can solve <b>several</b> problems (e.g. restart DAQ, restart computers).</td>
	        </tr>
	          <td>3</td>
	          <td>Experienced</td>
	          <td>I can perform all shift responsibilities. I have shifts regularly and I detect problems in the online spectra easily. I could teach others how to perform shift tasks successfully. I could manage everything alone.</td>
	          <td>I can solve <b>most</b> problems, I am comfortable with working in the hall and not afraid of replacing modules.</td>
	        </tr>
	        <tr>
	          <td>4</td>
	          <td>Expert</td>
	          <td>I can perform all shift responsibilities. I have shifts regularly and I’m up to date with the setup. I have already taught others how to perform shift tasks.</td>
	          <td>I can solve <b>complicated</b> problems and know only a few people who would know more than me.</td>
	        </tr>
	      </tbody>
	    </table>
	    ';

	/**
	 * A modal including the rating guide
	 *
	 * @var string
	 */
	protected $modal = '
	    <div class="modal fade rating-modal-lg" tabindex="-1" role="dialog" aria-labelledby="[LABEL]">
	      <div class="modal-dialog modal-lg">
	        <div class="modal-content">
	          <div class="modal-header">
	            <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
	            <h3 class="modal-title" id="[LABEL]">Rating Guidance for Shift Experience</h3>
	          </div>
	          <div class="modal-body">
	            [GUIDE]
	          </div>
	          <div class="modal-footer">
	            <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
	          </div>
	        </div>
	      </div>
	    </div>
	    ';

	/**
	 * Print the rating guide
	 */
	public function show()
	{
		echo $this->guide;
	}

	/**
	 * Print the rating guide within a modal
	 */
	public function modal($label = 'rating-guide-modal')
	{
		echo str_replace(array('[LABEL]', '[GUIDE]'), array($label, $this->guide), $this->modal);
	}
}
