<?php

require_once ('Estructura.php');
require_once("base/Runs.dao.base.php");
require_once("base/Runs.vo.base.php");
/** Page-level DocBlock .
 * 
 * @author alanboy
 * @package docs
 * 
 */
/** Runs Data Access Object (DAO).
 * 
 * Esta clase contiene toda la manipulacion de bases de datos que se necesita para 
 * almacenar de forma permanente y recuperar instancias de objetos {@link Runs }. 
 * @author alanboy
 * @access public
 * @package docs
 * 
 */
class RunsDAO extends RunsDAOBase
{
	/*
	 * Gets a boolean indicating whether there are runs that are not ready.
	 */
	public static final function PendingRuns($contest_id, $showAllRuns = false)
	{
		// Build SQL statement.
		$sql = "SELECT COUNT(*) FROM Runs WHERE contest_id = ? AND status != 'ready'";
		$val = array($contest_id);

		if (!$showAllRuns) {
			$sql .= ' AND test = 0';
		}

		global $conn;
		return $conn->GetOne($sql, $val) === 0;
	}

	/*
	 * Gets an array of the guids of the pending runs
	 */
	public static final function GetPendingRunsOfContest($contest_id, $showAllRuns = false)
	{
		// Build SQL statement.
		$sql = "SELECT guid FROM Runs WHERE contest_id = ? AND status != 'ready'";
		$val = array($contest_id);

		if (!$showAllRuns) 
		{
			$sql .= ' AND test = 0';
		}

		global $conn;
		$rs = $conn->Execute($sql, $val);

		$ar = array();
		foreach ($rs as $foo) 
		{                
			array_push($ar, $foo['guid']);
		}

		return $ar;
	}

	/*
	 * Gets the count of total runs sent to a given contest
	 */
	public static final function CountTotalRunsOfContest($contest_id, $showAllRuns = false)
	{
		// Build SQL statement.
		$sql = "SELECT COUNT(*) FROM Runs WHERE contest_id = ? ";
		$val = array($contest_id);

		if (!$showAllRuns) 
		{
			$sql .= ' AND test = 0';
		}

		global $conn;
		return $conn->GetOne($sql, $val);
	}
        
        /*
	 * Gets the count of total runs sent to a given contest by veredict
	 */
	public static final function CountTotalRunsOfContestByVeredict($contest_id, $veredict, $showAllRuns = false)
	{
		// Build SQL statement.
		$sql = "SELECT COUNT(*) FROM Runs WHERE contest_id = ? AND veredict = ? ";
		$val = array($contest_id, $veredict);

		if (!$showAllRuns) 
		{
			$sql .= ' AND test = 0';
		}

		global $conn;
		return $conn->GetOne($sql, $val);
	}

	/*
	 * Gets the largest queued time of a run in ms 
	 */
	public static final function GetLargestWaitTimeOfContest($contest_id, $showAllRuns = false)
	{
		// Build SQL statement.
		$sql = "SELECT * FROM Runs WHERE contest_id = ? AND status != 'ready' ORDER BY time ASC LIMIT 1";
		$val = array($contest_id);

		global $conn;
		$rs = $conn->GetRow($sql, $val);            

		if(count($rs) === 0)
		{
			return null;
		}

		$run = new Runs($rs);                        
		return array($run, time() - strtotime($run->getTime()));                        
	}

	/*
	 *  GetAllRelevantUsers
	 * 
	 */
	public static final function GetAllRelevantUsers($contest_id, $showAllRuns = false, $filterUsersBy = null)
	{
		// Build SQL statement
// Build SQL statement
                if (!$showAllRuns)
                {
                    $sql = "SELECT Users.user_id, username, Users.name from Users INNER JOIN ( SELECT DISTINCT Runs.user_id from Runs WHERE ( Runs.contest_id = ? AND Runs.status = 'ready' " . ($showAllRuns ? "" : " AND Runs.test = 0") . " ) ) RunsContests ON Users.user_id = RunsContests.user_id ". (!is_null($filterUsersBy) ? "WHERE Users.username LIKE ?" : "");

                    if (is_null($filterUsersBy))
                    {
                            $val = array($contest_id);
                    }
                    else
                    {
                            $val = array($contest_id, $filterUsersBy . "%");
                    }
                }
                else 
                {
                    $sql = "SELECT Users.user_id, username, Users.name from Users INNER JOIN Contests_Users ON Users.user_id = Contests_Users.user_id WHERE contest_id = ?";
                    $val = array($contest_id);
                }



		global $conn;
		$rs = $conn->Execute($sql, $val);

		$ar = array();
		foreach ($rs as $foo) {
			$bar =  new Users($foo);
			array_push( $ar,$bar);
		}

		return $ar;
	}

	/*
	 * 
	 * Get last run of a user
	 * 
	 */
	public static final function GetLastRun($contest_id, $problem_id, $user_id)
	{
		//Build SQL statement
		if ($contest_id == null) {
			$sql = "SELECT * from Runs where user_id = ? and problem_id = ? ORDER BY time DESC LIMIT 1";
			$val = array($user_id, $problem_id);
		} else {
			$sql = "SELECT * from Runs where user_id = ? and contest_id = ? and problem_id = ? ORDER BY time DESC LIMIT 1";
			$val = array($user_id, $contest_id, $problem_id);
		}

		global $conn;
		$rs = $conn->GetRow($sql, $val);            

		if(count($rs) === 0)
		{
			return null;
		}
		$bar = new Runs($rs);

		return $bar;
	}

	/*
	 * 
	 * Get best run of a user
	 * 
	 */
	public static final function GetBestRun($contest_id, $problem_id, $user_id, $finish_time, $showAllRuns)
	{
		//Build SQL statement
		$sql = "SELECT contest_score, submit_delay, guid, run_id from Runs where user_id = ? and contest_id = ? and problem_id = ? and status = 'ready' and time <= FROM_UNIXTIME(?) " . ($showAllRuns ? "" : " AND test = 0 ") . " ORDER BY contest_score DESC, submit_delay ASC  LIMIT 1";
		$val = array($user_id, $contest_id, $problem_id, $finish_time);

		global $conn;
		$rs = $conn->GetRow($sql, $val);

		return new Runs($rs);
	}

	/*
	 * Get number of runs before current.
	 */
	public static final function GetWrongRuns($contest_id, $problem_id, $user_id, $run_id, $showAllRuns)
	{
		//Build SQL statement
		$sql = "SELECT COUNT(*) AS wrong_runs FROM Runs WHERE user_id = ? AND contest_id = ? AND problem_id = ? AND veredict != 'JE' AND veredict != 'CE' AND run_id < ? ". ($showAllRuns ? "" : " AND test = 0 ");
		$val = array($user_id, $contest_id, $problem_id, $run_id);

		global $conn;
		$rs = $conn->GetRow($sql, $val);

		return $rs['wrong_runs'];
	}

	public static final function IsRunInsideSubmissionGap($contest_id, $problem_id, $user_id)
	{
		// Get last run
		$lastrun = self::GetLastRun($contest_id, $problem_id, $user_id);

		if(is_null($lastrun))
		{            
			return true;
		}

		if ($contest_id == null) {
			// Default is 2 minutes.
			$submission_gap = 120;
		} else {
			// Get submissions gap
			$contest = ContestsDAO::getByPK($contest_id);
			$submission_gap = (int)$contest->getSubmissionsGap();
		}

		// Giving 10 secs as gift
		return time() >= (strtotime($lastrun->getTime()) + $submission_gap - 10);
	}
}