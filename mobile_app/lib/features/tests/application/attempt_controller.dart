import 'dart:async';

import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/utils/json.dart';
import '../data/test_repository.dart';


/// Per-question state (mutable inside the session, the session object is re-emitted).
class QState {
  int? answer;
  bool marked = false;
  bool visited = false;
  int time = 0;

  /// a = answered, na = visited not answered, m = marked, ma = marked + answered, '' = not visited
  String get status {
    if (marked && answer != null) return 'ma';
    if (marked) return 'm';
    if (answer != null) return 'a';
    if (visited) return 'na';
    return '';
  }
}

enum AttemptEvent { sectionEnded, testEnded }

class AttemptSession {
  AttemptSession({
    required this.testId,
    required this.attemptId,
    required this.title,
    required this.sectionSeconds,
    this.sectional = true,
    required this.sections,
    required this.q,
    required this.left,
    required this.spent,
    required this.done,
    required this.reportReasons,
    this.s = 0,
    this.i = 0,
    this.running = true,
    this.finished = false,
    this.event,
    this.version = 0,
  });

  final int testId;
  final String attemptId, title;
  final int sectionSeconds;

  /// false = one timer for the whole test, free movement (sections are merged into one list).
  final bool sectional;
  final List<Json> sections;
  final List<List<QState>> q;
  final List<int> left, spent;
  final List<bool> done;
  final List<String> reportReasons;
  int s, i;
  bool running, finished;
  AttemptEvent? event;
  final int version;

  Json get section => sections[s];
  Json get question => section.l('questions')[i];
  QState get current => q[s][i];
  int get totalLeft => left.fold(0, (a, b) => a + b);
  bool get isLastSection => s == sections.length - 1;
  int get perSection => q.isEmpty ? 0 : q[s].length;

  Map<String, int> counts(int sec) {
    final c = {'answered': 0, 'skipped': 0, 'marked': 0, 'not_visited': 0};
    for (final x in q[sec]) {
      if (x.answer != null) {
        c['answered'] = c['answered']! + 1;
      } else if (x.marked) {
        c['marked'] = c['marked']! + 1;
      } else if (x.visited) {
        c['skipped'] = c['skipped']! + 1;
      } else {
        c['not_visited'] = c['not_visited']! + 1;
      }
    }
    return c;
  }

  AttemptSession bump() => AttemptSession(
        testId: testId, attemptId: attemptId, title: title, sectionSeconds: sectionSeconds, sectional: sectional, sections: sections,
        q: q, left: left, spent: spent, done: done, reportReasons: reportReasons,
        s: s, i: i, running: running, finished: finished, event: event, version: version + 1,
      );

  /// Server format: [{id: test_question_id, selected: [option id], marked, visited, time_spent}]
  List<Json> toAnswers() => [
        for (var si = 0; si < q.length; si++)
          for (var qi = 0; qi < q[si].length; qi++)
            if (q[si][qi].visited || q[si][qi].answer != null || q[si][qi].marked)
              {
                'id': sections[si].l('questions')[qi].i('id'),
                'selected': q[si][qi].answer == null ? <int>[] : [sections[si].l('questions')[qi].ls('option_ids').map(int.parse).elementAt(q[si][qi].answer!)],
                'marked': q[si][qi].marked,
                'visited': q[si][qi].visited,
                'time_spent': q[si][qi].time,
              },
      ];
}

final attemptControllerProvider = AsyncNotifierProvider.autoDispose<AttemptController, AttemptSession?>(AttemptController.new);

class AttemptController extends AsyncNotifier<AttemptSession?> {
  Timer? _tick;
  Timer? _autosave;

  TestRepository get _repo => ref.read(testRepositoryProvider);
  AttemptSession? get _s => state.value;

  @override
  Future<AttemptSession?> build() async {
    ref.onDispose(() {
      _tick?.cancel();
      _autosave?.cancel();
    });
    return null;
  }

  /// POST /app/tests/{id}/start – resumes an unfinished attempt with its saved answers and server clock.
  Future<void> start(int testId, String language) async {
    state = const AsyncLoading();
    state = await AsyncValue.guard(() async {
      final p = await _repo.start(testId, language);
      final test = p.m('test');
      final sectional = test.b('sectional_timing');
      final remaining = p.i('remaining_sec', test.i('duration_min', 60) * 60);
      final cur0 = p.i('current_section');

      // Server question → the shape the attempt screen uses (texts per language, option index answers)
      Json mapQ(Json x) => {
            'id': x.i('id'),
            'question_id': x.i('question_id'),
            'no': x.i('no'),
            'type': x.s('type'),
            'marks': x.n('marks', 1),
            'negative': x.n('negative'),
            'q': x['text'],
            'options': [for (final o in x.l('options')) o['text']],
            'option_ids': [for (final o in x.l('options')) '${o.i('id')}'],
            'state': x.m('state'),
          };

      List<Json> sections = [
        for (final s in p.l('sections'))
          {
            'id': s.i('id'),
            'name': s.s('name'),
            'short': s.s('short_name', s.s('name')),
            'en_only': s.b('en_only'),
            'minutes': s.i('duration_min', test.i('duration_min')),
            'questions': [for (final x in s.l('questions')) mapQ(x)],
          },
      ];
      if (!sectional) {
        sections = [
          {
            'id': 0,
            'name': test.s('title'),
            'short': 'All',
            'en_only': false,
            'minutes': test.i('duration_min'),
            'questions': [for (final s in sections) ...s.l('questions')],
          },
        ];
      }
      final cur = sectional ? cur0.clamp(0, sections.length - 1) : 0;
      final q = [
        for (final s in sections)
          [
            for (final x in s.l('questions'))
              () {
                final st = x.m('state');
                final sel = st['selected'] is List && (st['selected'] as List).isNotEmpty ? '${(st['selected'] as List).first}' : null;
                final idx = sel == null ? -1 : x.ls('option_ids').indexOf(sel);
                return QState()
                  ..answer = idx < 0 ? null : idx
                  ..marked = st.b('marked')
                  ..visited = st.b('visited')
                  ..time = st.i('time_spent');
              }(),
          ],
      ];
      final secSeconds = [for (final s in sections) (sectional ? s.i('minutes') : test.i('duration_min')) * 60];
      final session = AttemptSession(
        testId: testId,
        attemptId: p.s('attempt'),
        title: test.s('title'),
        sectionSeconds: secSeconds[cur],
        sectional: sectional,
        sections: sections,
        q: q,
        left: [for (var k = 0; k < sections.length; k++) k < cur ? 0 : (k == cur ? remaining : secSeconds[k])],
        spent: [for (var k = 0; k < sections.length; k++) k == cur ? (secSeconds[k] - remaining).clamp(0, secSeconds[k]) : 0],
        done: [for (var k = 0; k < sections.length; k++) k < cur],
        reportReasons: [for (final r in reportReasons) r.$2],
        s: cur,
      );
      session.current.visited = true;
      return session;
    });
    _tick?.cancel();
    _tick = Timer.periodic(const Duration(seconds: 1), (_) => _onTick());
    _autosave?.cancel();
    _autosave = Timer.periodic(const Duration(seconds: 20), (_) => sync());
  }

  void _emit() {
    final s = _s;
    if (s != null) state = AsyncData(s.bump());
  }

  void _onTick() {
    final s = _s;
    if (s == null || !s.running || s.finished) return;
    s.left[s.s] = (s.left[s.s] - 1).clamp(0, 1 << 30);
    s.spent[s.s]++;
    s.current.time++;
    if (s.left[s.s] == 0) {
      s.running = false;
      s.done[s.s] = true;
      s.event = s.isLastSection ? AttemptEvent.testEnded : AttemptEvent.sectionEnded;
    }
    _emit();
  }

  void setRunning(bool v) {
    final s = _s;
    if (s == null || s.finished) return;
    if (v && s.left[s.s] == 0) return;
    s.running = v;
    _emit();
  }

  void clearEvent() {
    _s?.event = null;
    _emit();
  }

  void select(int option) {
    _s?.current.answer = option;
    _emit();
  }

  void clear() {
    _s?.current.answer = null;
    _emit();
  }

  void toggleMark() {
    final c = _s?.current;
    if (c != null) c.marked = !c.marked;
    _emit();
  }

  void goTo(int index) {
    final s = _s;
    if (s == null) return;
    s.i = index.clamp(0, s.perSection - 1);
    s.current.visited = true;
    _emit();
  }

  /// Save & Next. Returns false on the last question of the section.
  bool next() {
    final s = _s;
    if (s == null) return false;
    if (s.i >= s.perSection - 1) return false;
    goTo(s.i + 1);
    return true;
  }

  /// Moves to the next section (after time ends or Submit section).
  void nextSection() {
    final s = _s;
    if (s == null || s.isLastSection) return;
    s.done[s.s] = true;
    s.left[s.s] = 0;
    s.s++;
    s.i = 0;
    s.current.visited = true;
    s.event = null;
    s.running = true;
    _emit();
    _repo.nextSection(s.attemptId, s.toAnswers()).then((r) {
      // trust the server clock for the new section
      final left = r.i('remaining_sec', -1);
      if (left >= 0 && _s != null) {
        _s!.left[_s!.s] = left;
        _emit();
      }
    }).catchError((_) {});
  }

  /// Report the current question (wrong key, translation, typo…).
  Future<void> report(int reasonIndex, String note) async {
    final s = _s;
    if (s == null) return;
    await _repo.report(s.question.i('question_id'), reportReasons[reasonIndex.clamp(0, reportReasons.length - 1)].$1, note: note);
  }

  Future<void> sync() async {
    final s = _s;
    if (s == null || s.finished) return;
    try {
      final r = await _repo.sync(s.attemptId, s.toAnswers());
      // keep the phone clock in line with the server (app in background, clock changes)
      final left = r.i('remaining_sec', -1);
      if (left >= 0 && (left - s.left[s.s]).abs() > 5) {
        s.left[s.s] = left;
        _emit();
      }
    } catch (_) {/* retried on next autosave */}
  }

  /// POST /attempts/{id}/submit → returns attempt id for the result screen.
  Future<String> submit() async {
    final s = _s!;
    s.running = false;
    s.finished = true;
    for (var k = 0; k < s.done.length; k++) {
      s.done[k] = true;
    }
    _emit();
    await _repo.submit(s.attemptId, s.toAnswers());
    _tick?.cancel();
    _autosave?.cancel();
    return s.attemptId;
  }
}
