<?php
/**
 * Created by PhpStorm.
 * User: ari
 * Date: 1/27/2015
 * Time: 1:56 PM
 */
namespace Site\Song;

use CPath\Build\IBuildable;
use CPath\Build\IBuildRequest;
use CPath\Render\HTML\Attribute\Attributes;
use CPath\Render\HTML\Element\Form\HTMLButton;
use CPath\Render\HTML\Element\Form\HTMLForm;
use CPath\Render\HTML\Element\Form\HTMLInputField;
use CPath\Render\HTML\Element\Form\HTMLSelectField;
use CPath\Render\HTML\Element\Form\HTMLTextAreaField;
use CPath\Render\HTML\Element\HTMLElement;
use CPath\Render\HTML\Header\HTMLHeaderScript;
use CPath\Render\HTML\Header\HTMLHeaderStyleSheet;
use CPath\Render\HTML\Header\HTMLMetaTag;
use CPath\Request\Executable\ExecutableRenderer;
use CPath\Request\Executable\IExecutable;
use CPath\Request\Form\IFormRequest;
use CPath\Request\IRequest;
use CPath\Request\Session\ISessionRequest;
use CPath\Request\Validation\RequiredValidation;
use CPath\Response\Common\RedirectResponse;
use CPath\Response\IResponse;
use CPath\Route\IRoutable;
use CPath\Route\RouteBuilder;
use Site\Song\DB\SongEntry;
use Site\SiteMap;
use Site\Song\DB\SongTable;
use Site\Song\Genre\DB\GenreEntry;
use Site\Song\Genre\DB\SongGenreEntry;
use Site\Song\System\DB\SongSystemEntry;
use Site\Song\System\DB\SystemEntry;

class CreateSong implements IExecutable, IBuildable, IRoutable
{
	const TITLE = 'Create a new Song';

	const FORM_ACTION = '/create/song/';
	const FORM_ACTION2 = '/songs';
	const FORM_METHOD = 'POST';
	const FORM_NAME = 'create-song';

    const PARAM_SONG_TITLE = 'song-title';
    const PARAM_SONG_GENRES = 'song-genres';
    const PARAM_SONG_SYSTEMS = 'song-systems';
    const PARAM_SONG_DESCRIPTION = 'song-description';

    /**
	 * Execute a command and return a response. Does not render
	 * @param IRequest $Request
	 * @throws \Exception
	 * @return IResponse the execution response
	 */
	function execute(IRequest $Request) {
		$SessionRequest = $Request;
		if (!$SessionRequest instanceof ISessionRequest)
			throw new \Exception("Session required");

        $systemList = SystemEntry::getAll();
        $genreList = GenreEntry::getAll();

		$Form = new HTMLForm(self::FORM_METHOD, $Request->getPath(), self::FORM_NAME,
			new HTMLMetaTag(HTMLMetaTag::META_TITLE, self::TITLE),
			new HTMLHeaderScript(__DIR__ . '/assets/song.js'),
			new HTMLHeaderStyleSheet(__DIR__ . '/assets/song.css'),

			new HTMLElement('fieldset', 'fieldset-create-song',
				new HTMLElement('legend', 'legend-song', self::TITLE),

                new HTMLElement('label', null, "Song Title:<br/>",
                    new HTMLInputField(self::PARAM_SONG_TITLE,
                        new Attributes('placeholder', 'My Song Title'),
                        new RequiredValidation()
                    )
                ),


                "<br/><br/>",
                new HTMLElement('label', null, "Description:<br/>",
                    new HTMLTextAreaField(self::PARAM_SONG_DESCRIPTION,
                        new Attributes('placeholder', 'Enter a song description'),
                        new Attributes('rows', 10, 'cols', 40),
                        new RequiredValidation()
                    )
                ),

                "<br/><br/>",
                new HTMLElement('label', null, "Game Systems:<br/>",
                    new HTMLSelectField(self::PARAM_SONG_SYSTEMS . '[]', $systemList,
                        new Attributes('multiple', 'multiple'),
                        new RequiredValidation()
                    )
                ),

                "<br/><br/>",
                new HTMLElement('label', null, "Genres:<br/>",
                    new HTMLSelectField(self::PARAM_SONG_GENRES . '[]', $genreList,
                        new Attributes('multiple', 'multiple'),
                        new RequiredValidation()
                    )
                ),

				"<br/><br/>",
				new HTMLButton('submit', 'Create', 'submit')
			),
			"<br/>"
		);

		if(!$Request instanceof IFormRequest)
			return $Form;

        $Form->setFormValues($Request);

        $genres = $Form->validateField($Request, self::PARAM_SONG_GENRES);
        $systems = $Form->validateField($Request, self::PARAM_SONG_SYSTEMS);
        $title = $Form->validateField($Request, self::PARAM_SONG_TITLE);
        $description = $Form->validateField($Request, self::PARAM_SONG_DESCRIPTION);

        $MatchingSong = SongEntry::table()
            ->select()
            ->where(SongTable::COLUMN_TITLE, $title)
            ->where(SongTable::COLUMN_STATUS, SongEntry::STATUS_PUBLISHED, '&?')
            ->fetch();

        if($MatchingSong)
            throw new \InvalidArgumentException("A published song already has this name. What gives!?");

		$Song = SongEntry::create($Request, $title, $description);
        foreach($genres as $genre) {
            SongGenreEntry::addToSong($Request, $Song->getID(), $genre);
        }

        foreach($systems as $system) {
            SongSystemEntry::addToSong($Request, $Song->getID(), $system);
        }

        return new RedirectResponse(ManageSong::getRequestURL($Song->getID()), "Song created successfully. Redeflecting...", 5);
	}

	// Static

	public static function getRequestURL() {
		return self::FORM_ACTION;
	}

	/**
	 * Route the request to this class object and return the object
	 * @param IRequest $Request the IRequest inst for this render
	 * @param array|null $Previous all previous response object that were passed from a handler, if any
	 * @param null|mixed $_arg [varargs] passed by route map
	 * @return void|bool|Object returns a response object
	 * If nothing is returned (or bool[true]), it is assumed that rendering has occurred and the request ends
	 * If false is returned, this static handler will be called again if another handler returns an object
	 * If an object is returned, it is passed along to the next handler
	 */
	static function routeRequestStatic(IRequest $Request, Array &$Previous = array(), $_arg = null) {
		$Render = new ExecutableRenderer(new static(), true);
		$Render->execute($Request);
		return $Render;
	}

	/**
	 * Handle this request and render any content
	 * @param IBuildRequest $Request the build request inst for this build session
	 * @return void
	 * @build --disable 0
	 * Note: Use doctag 'build' with '--disable 1' to have this IBuildable class skipped during a build
	 */
	static function handleBuildStatic(IBuildRequest $Request) {
		$RouteBuilder = new RouteBuilder($Request, new SiteMap());
		$RouteBuilder->writeRoute('ANY ' . self::FORM_ACTION, __CLASS__);
		$RouteBuilder->writeRoute('ANY ' . self::FORM_ACTION2, __CLASS__,
			IRequest::NAVIGATION_ROUTE |
			IRequest::MATCH_SESSION_ONLY,
			"Songs");
	}
}