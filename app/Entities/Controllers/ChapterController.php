<?php

namespace BookStack\Entities\Controllers;

use Illuminate\Support\Facades\DB;
use BookStack\Activity\Models\View;
use BookStack\Activity\Tools\UserEntityWatchOptions;
use BookStack\Entities\Queries\ChapterQueries;
use BookStack\Entities\Queries\EntityQueries;
use BookStack\Entities\Repos\ChapterRepo;
use BookStack\Entities\Tools\BookContents;
use BookStack\Entities\Tools\Cloner;
use BookStack\Entities\Tools\NextPreviousContentLocator;
use BookStack\Http\Controller;
use BookStack\Permissions\Permission;
use BookStack\References\ReferenceFetcher;
use Illuminate\Http\Request;

class ChapterController extends Controller
{
    public function __construct(
        protected ChapterRepo $chapterRepo,
        protected ChapterQueries $queries,
        protected EntityQueries $entityQueries,
        protected ReferenceFetcher $referenceFetcher,
    ) {
    }

    /**
     * Cập nhật Chapter -> Cập nhật trạng thái
     */
    public function update(Request $request, string $bookSlug, string $chapterSlug)
    {
        $validated = $this->validate($request, ['name' => ['required', 'string', 'max:255']]);
        $chapter = $this->queries->findVisibleBySlugsOrFail($bookSlug, $chapterSlug);
        $this->checkOwnablePermission(Permission::ChapterUpdate, $chapter);

        $chapter = $this->chapterRepo->update($chapter, $validated);

        // ✅ DÒNG MỚI: Đồng bộ trạng thái duyệt
        $chapter->syncApprovalStatus();

        return redirect($chapter->getUrl());
    }

    /**
     * Sao chép Chapter -> Cũng phải chờ duyệt
     */
    public function copy(Request $request, Cloner $cloner, string $bookSlug, string $chapterSlug)
    {
        $chapter = $this->queries->findVisibleBySlugsOrFail($bookSlug, $chapterSlug);
        $entitySelection = $request->get('entity_selection') ?: null;
        $newParentBook = $entitySelection ? $this->entityQueries->findVisibleByStringIdentifier($entitySelection) : $chapter->getParent();

        $this->checkOwnablePermission(Permission::ChapterCreate, $newParentBook);
        $chapterCopy = $cloner->cloneChapter($chapter, $newParentBook, $request->get('name') ?: $chapter->name);

        // ✅ DÒNG MỚI: Gọi Trait cho bản copy
        $chapterCopy->syncApprovalStatus();

        return redirect($chapterCopy->getUrl());
    }

    /**
     * Hiển thị Chapter (Scope đã tự chặn 404 nếu chưa duyệt, ở đây chỉ xử lý Alert)
     */
    public function show(string $bookSlug, string $chapterSlug)
    {
        $chapter = $this->queries->findVisibleBySlugsOrFail($bookSlug, $chapterSlug);
        
        // 1. KIỂM TRA TRẠNG THÁI ĐỂ HIỆN THÔNG BÁO (ALERT)
        $approval = DB::table('duyet_bai')
            ->where('entity_id', $chapter->id)
            ->where('entity_type', 'chapter')
            ->first();
        $status = $approval ? $approval->trang_thai : 'cho_duyet';

        $user = auth()->user();
        $alertHtml = "";

        if ($status === 'cho_duyet') {
            // Logic check Sếp (Đồng bộ với Trait)
            $isBoss = $user && ($user->hasSystemRole('admin') || $user->roles()->whereIn('id', [7, 8])->exists());
            $isAuthor = $user && ((int)$user->id === (int)$chapter->created_by);

            if ($isBoss) {
                // Alert cho Sếp
                $alertHtml = "
                <div style='background:#fff3cd; color:#856404; padding:15px; border:1px solid #ffeeba; margin-bottom:20px; border-radius:4px; display:flex; justify-content:space-between; align-items:center;'>
                    <span>⚠️ <b>Quản lý:</b> Chương này đang chờ duyệt.</span>
                    <form action='".url('/approve-content')."' method='POST' style='margin:0;'>
                        <input type='hidden' name='_token' value='".csrf_token()."'>
                        <input type='hidden' name='id' value='".$chapter->id."'>
                        <input type='hidden' name='type' value='chapter'>
                        <button type='submit' style='background:#28a745; color:white; border:none; padding:7px 15px; border-radius:4px; cursor:pointer; font-weight:bold;'>✅ DUYỆT NGAY</button>
                    </form>
                </div>";
            } elseif ($isAuthor) {
                // Alert cho Tác giả
                $alertHtml = "<div style='background:#d1ecf1; color:#0c5460; padding:15px; border:1px solid #bee5eb; margin-bottom:20px; border-radius:4px;'>ℹ️ Nội dung này đang chờ duyệt.</div>";
            }
        }

        $sidebarTree = (new BookContents($chapter->book))->getTree();
        View::incrementFor($chapter);

        return view('chapters.show', [
            'book' => $chapter->book, 
            'chapter' => $chapter, 
            'current' => $chapter,
            'sidebarTree' => $sidebarTree, 
            'pages' => $this->entityQueries->pages->visibleForChapterList($chapter->id)->get(),
            'watchOptions' => new UserEntityWatchOptions(user(), $chapter),
            'next' => (new NextPreviousContentLocator($chapter, $sidebarTree))->getNext(),
            'previous' => (new NextPreviousContentLocator($chapter, $sidebarTree))->getPrevious(),
            'referenceCount' => $this->referenceFetcher->getReferenceCountToEntity($chapter),
            'alertHtml' => $alertHtml // Gửi biến alert ra view
        ]);
    }

    /**
     * Show the form for creating a new chapter.
     */
    public function create(string $bookSlug)
    {
        // ✅ SỬA LẠI DÒNG NÀY: Dùng entityQueries->books để tìm dự án
        $book = $this->entityQueries->books->findVisibleBySlugOrFail($bookSlug);
        
        // 🔒 CHẶN: Chỉ Admin hoặc Chủ dự án mới được tạo Hạng mục
        if (auth()->id() != $book->owned_by && !auth()->user()->hasSystemRole('admin')) {
             $this->showPermissionError();
        }

        $this->checkOwnablePermission(Permission::ChapterCreate, $book);

        $this->setPageTitle(trans('entities.chapters_new'));
        return view('chapters.create', ['book' => $book, 'current' => $book]);
    }

    /**
     * Store a new chapter.
     */
    public function store(Request $request, string $bookSlug)
    {
        // ✅ SỬA LẠI DÒNG NÀY
        $book = $this->entityQueries->books->findVisibleBySlugOrFail($bookSlug);

        // 🔒 CHẶN: Chỉ Admin hoặc Chủ dự án mới được lưu Hạng mục
        if (auth()->id() != $book->owned_by && !auth()->user()->hasSystemRole('admin')) {
             $this->showPermissionError();
        }

        $this->checkOwnablePermission(Permission::ChapterCreate, $book);

        $validated = $this->validate($request, [
            'name' => ['required', 'string', 'max:255'],
            'description_html' => ['string', 'max:2000'],
            'tags' => ['array'],
        ]);

        $chapter = $this->chapterRepo->create($validated, $book);
        
        // Đồng bộ trạng thái duyệt
        $chapter->syncApprovalStatus();

        return redirect($chapter->getUrl());
    }
    
    public function edit(string $bookSlug, string $chapterSlug) { 
        $chapter = $this->queries->findVisibleBySlugsOrFail($bookSlug, $chapterSlug); 
        return view('chapters.edit', ['book' => $chapter->book, 'chapter' => $chapter]); 
    }
    public function destroy(string $bookSlug, string $chapterSlug)
    {
        $chapter = $this->queries->findVisibleBySlugOrFail($chapterSlug);
        
        // 🔒 CHẶN: Kiểm tra chủ của CUỐN SÁCH cha
        $book = $chapter->book;
        if (auth()->id() != $book->owned_by && !auth()->user()->hasSystemRole('admin')) {
             $this->showPermissionError();
        }

        $this->checkOwnablePermission(Permission::ChapterDelete, $chapter);
        $this->chapterRepo->destroy($chapter);
        return redirect($chapter->getBook()->getUrl());
    }
}