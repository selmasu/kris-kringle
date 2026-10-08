<?php

class UserPermissions {
    /**
     * @var UsersPermissions
     */
    private $users;
    /**
     * @var PagesPermissions
     */
    private $pages;
    /**
     * @var TemplatesPermissions
     */
    private $templates;
    /**
     * @var ArticlesPermissions
     */
    private $articles;
    /**
     * @var ContentAreasPermissions
     */
    private $contentAreas;
    /**
     * @var GalleriesPermissions
     */
    private $galleries;
    /**
     * @var FileRepoPermissions
     */
    private $fileRepo;
    /**
     * @var PdfTemplatesPermissions
     */
    private $pdfTemplates;

	/**
     * @param UsersPermissions $users
	 * @return UserPermissions
	 */
    public function setUsers(UsersPermissions $users) {
        $this->users = $users;
        return $this;
    }

    /**
     * Returns the Users Permissions
     * @return UsersPermissions
     */
    public function users() {
        if (!$this->users) {
            $this->users = UsersPermissions::create();
        }

        return $this->users;
    }

    /**
     * @param PagesPermissions $pages
     * @return UserPermissions
     */
    public function setPages( PagesPermissions $pages )
    {
        $this->pages = $pages;
        return $this;
    }

    /**
     * Returns the Pages Permissions
     * @return PagesPermissions
     */
    public function pages()
    {
        if (!$this->pages) {
            $this->pages = PagesPermissions::create();
        }

        return $this->pages;
    }

    /**
     * @param TemplatesPermissions $templates
     * @return UserPermissions
     */
    public function setTemplates( TemplatesPermissions $templates )
    {
        $this->templates = $templates;
        return $this;
    }

    /**
     * Returns the Templates Permissions
     * @return TemplatesPermissions
     */
    public function templates()
    {
        if (!$this->templates) {
            $this->templates = TemplatesPermissions::create();
        }

        return $this->templates;
    }

    /**
     * @param ArticlesPermissions $articles
     * @return UserPermissions
     */
    public function setArticles( ArticlesPermissions $articles )
    {
        $this->articles = $articles;
        return $this;
    }

    /**
     * Returns the Articles Permissions
     * @return ArticlesPermissions
     */
    public function articles()
    {
        return ( $this->articles ) ? $this->articles : ArticlesPermissions::create();
    }

    public function setContentAreas( ContentAreasPermissions $contentAreas )
    {
        $this->contentAreas = $contentAreas;
        return $this;
    }

    /**
     * Returns the Content Areas Permissions
     * @return ContentAreasPermissions
     */
    public function contentAreas()
    {
        if (!$this->contentAreas) {
            $this->contentAreas = ContentAreasPermissions::create();
        }

        return $this->contentAreas;
    }

    /**
     * @param GalleriesPermissions $galleries
     * @return UserPermissions
     */
    public function setGalleries( GalleriesPermissions $galleries )
    {
        $this->galleries = $galleries;
        return $this;
    }

    /**
     * Returns the Galleries Permissions
     * @return GalleriesPermissions
     */
    public function galleries()
    {
        if (!$this->galleries) {
            $this->galleries = GalleriesPermissions::create();
        }

        return $this->galleries;
    }

    /**
     * @param FileRepoPermissions $fileRepo
     * @return UserPermissions
     */
    public function setFileRepo( FileRepoPermissions $fileRepo )
    {
        $this->fileRepo = $fileRepo;
        return $this;
    }

    /**
     * Returns the File Repo Permissions
     * @return FileRepoPermissions
     */
    public function fileRepo()
    {
        if (!$this->fileRepo) {
            $this->fileRepo = FileRepoPermissions::create();
        }

        return $this->fileRepo;
    }

    /**
     * @param PdfTemplatesPermissions $pdfTemplates
     * @return UserPermissions
     */
    public function setPdfTemplates( PdfTemplatesPermissions $pdfTemplates )
    {
        $this->pdfTemplates = $pdfTemplates;
        return $this;
    }

    /**
     * Returns the PDF Templates Permissions
     * @return PdfTemplatesPermissions
     */
    public function pdfTemplates()
    {
        if (!$this->pdfTemplates) {
            $this->pdfTemplates = PdfTemplatesPermissions::create();
        }

        return $this->pdfTemplates;
    }

    /**
     *
     * Sets permissions from an array (rows from DB)
     * @param array $permissions
     * @return UserPermissions
     */
    public function fromArray($permissions) {
        foreach ($permissions as $permission) {
            $permissionInt = (int)$permission['permission'];

            if ($permission['appId'] == App::USERS) {
                $this->setUsers(UsersPermissions::create()->setPermissions($permissionInt));
            } else if( $permission['appId'] == App::PAGES ) {
                $this->setPages( PagesPermissions::create()->setPermissions( $permissionInt ) );
            } else if( $permission['appId'] == App::TEMPLATES ) {
                $this->setTemplates( TemplatesPermissions::create()->setPermissions( $permissionInt ) );
            } else if( $permission['appId'] == App::ARTICLES ) {
                $this->setArticles( ArticlesPermissions::create()->setPermissions( $permissionInt ) );
            } else if( $permission['appId'] == App::CONTENT_AREAS ) {
                $this->setContentAreas( ContentAreasPermissions::create()->setPermissions( $permissionInt ) );
            } else if( $permission['appId'] == App::GALLERIES ) {
                $this->setGalleries( GalleriesPermissions::create()->setPermissions( $permissionInt ) );
            } else if( $permission['appId'] == App::FILE_REPO ) {
                $this->setFileRepo( FileRepoPermissions::create()->setPermissions( $permissionInt ) );
            } else if( $permission['appId'] == App::PDF_TEMPLATES ) {
                $this->setPdfTemplates( PdfTemplatesPermissions::create()->setPermissions( $permissionInt ) );
            }
        }

        return $this;
    }

    /**
     *
     * Sets permissions from a user array (posted from user)
     * @param array $user
     * @return UserPermissions
     */
    public function fromUserArray($user) {
        $this->setUsers(UsersPermissions::create());
        $this->setPages( PagesPermissions::create() );
        $this->setTemplates( TemplatesPermissions::create() );
        $this->setArticles( ArticlesPermissions::create() );
        $this->setContentAreas( ContentAreasPermissions::create() );
        $this->setGalleries( GalleriesPermissions::create() );
        $this->setFileRepo( FileRepoPermissions::create() );
        $this->setPdfTemplates( PdfTemplatesPermissions::create() );

        if (isset($user['usersManage'])) {
            $this->users()->setCanManage(Permissions::MANAGE);
        }
        if( isset( $user['pagesManage'] ) ) {
            $this->pages()->setCanManage( Permissions::MANAGE );
        }
        if( isset( $user['templatesManage'] ) ) {
            $this->templates()->setCanManage( Permissions::MANAGE );
        }
        if( isset( $user['articlesManage'] ) ) {
            $this->articles()->setCanManage( Permissions::MANAGE );
        }
        if( isset( $user['contentAreasManage'] ) ) {
            $this->contentAreas()->setCanManage( Permissions::MANAGE );
        }
        if( isset( $user['galleriesManage'] ) ) {
            $this->galleries()->setCanManage( Permissions::MANAGE );
        }
        if( isset( $user['fileRepoManage'] ) ) {
            $this->fileRepo()->setCanManage( Permissions::MANAGE );
        }
        if( isset( $user['pdfTemplatesManage'] ) ) {
            $this->pdfTemplates()->setCanManage( Permissions::MANAGE );
        }

        return $this;
    }

    /**
     * Converts permissions to a flat array for remote service API
     * @return array
     */
    public function toArray() {
        $permissions = array();
        if ($this->users) {
            $permissions = array_merge($permissions, $this->users()->toArray());
        }
        if( $this->pages ) {
            $permissions = array_merge( $permissions, $this->pages()->toArray() );
        }
        if( $this->templates ) {
            $permissions = array_merge( $permissions, $this->templates()->toArray() );
        }
        if( $this->articles ) {
            $permissions = array_merge( $permissions, $this->articles()->toArray() );
        }
        if( $this->contentAreas ) {
            $permissions = array_merge( $permissions, $this->contentAreas()->toArray() );
        }
        if( $this->galleries ) {
            $permissions = array_merge( $permissions, $this->galleries()->toArray() );
        }
        if( $this->fileRepo ) {
            $permissions = array_merge( $permissions, $this->fileRepo()->toArray() );
        }
        if( $this->pdfTemplates ) {
            $permissions = array_merge( $permissions, $this->pdfTemplates()->toArray() );
        }

        return $permissions;
    }

    /**
     * Sets the user's permission to full access to all apps
     * @return UserPermissions
     */
    public function grantFullAccess() {
        $this->users()->setCanManage(Permissions::MANAGE);
        $this->pages()->setCanManage( Permissions::MANAGE );
        $this->templates()->setCanManage( Permissions::MANAGE );
        $this->articles()->setCanManage( Permissions::MANAGE );
        $this->contentAreas()->setCanManage( Permissions::MANAGE );
        $this->galleries()->setCanManage( Permissions::MANAGE );
        $this->fileRepo()->setCanManage( Permissions::MANAGE );
        $this->pdfTemplates()->setCanManage( Permissions::MANAGE);

        return $this;
    }

    /**
     * Static creator
     * @return UserPermissions
     */
    public static function create() {
        return new self;
    }
}