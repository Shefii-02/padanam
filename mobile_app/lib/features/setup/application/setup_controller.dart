import 'dart:typed_data';

import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/utils/json.dart';
import '../../auth/application/auth_controller.dart';
import '../../auth/data/user_model.dart';
import '../data/setup_repository.dart';

class SetupDraft {
  SetupDraft({
    this.step = 0,
    this.avatar = '🧑‍🎓',
    this.photo,
    this.name = '',
    this.gender,
    this.email = '',
    DateTime? dob,
    this.district,
    this.outsideKerala = false,
    this.state = '',
    this.town = '',
    this.pincode = '',
    this.qualification,
    this.exams = const [],
    this.posts = const {},
    this.level = 'Some preparation',
    this.aim = 'Get selected',
    this.attempt = '1st attempt',
    this.hours = 2,
    this.days = const [true, true, true, true, true, true, false],
    this.slot = 'Evening',
    this.reminder = true,
  }) : dob = dob ?? DateTime(2001, 6, 15);

  final int step;
  final String avatar;
  final Uint8List? photo;
  final String name;
  final String? gender;
  final String email;
  final DateTime dob;
  final String? district;
  final bool outsideKerala;
  final String state, town, pincode;
  final String? qualification;
  final List<String> exams;
  final Map<String, String> posts;
  final String level, aim, attempt;
  final int hours;
  final List<bool> days;
  final String slot;
  final bool reminder;

  int get age {
    final now = DateTime.now();
    var a = now.year - dob.year;
    if (DateTime(now.year, dob.month, dob.day).isAfter(now)) a--;
    return a;
  }

  int get studyDays => days.where((d) => d).length;

  SetupDraft copyWith({
    int? step, String? avatar, Uint8List? photo, bool clearPhoto = false, String? name, String? gender, String? email, DateTime? dob,
    String? district, bool? outsideKerala, String? state, String? town, String? pincode, String? qualification,
    List<String>? exams, Map<String, String>? posts, String? level, String? aim, String? attempt, int? hours,
    List<bool>? days, String? slot, bool? reminder,
  }) =>
      SetupDraft(
        step: step ?? this.step, avatar: avatar ?? this.avatar, photo: clearPhoto ? null : (photo ?? this.photo),
        name: name ?? this.name, gender: gender ?? this.gender, email: email ?? this.email, dob: dob ?? this.dob,
        district: district ?? this.district, outsideKerala: outsideKerala ?? this.outsideKerala, state: state ?? this.state,
        town: town ?? this.town, pincode: pincode ?? this.pincode, qualification: qualification ?? this.qualification,
        exams: exams ?? this.exams, posts: posts ?? this.posts, level: level ?? this.level, aim: aim ?? this.aim,
        attempt: attempt ?? this.attempt, hours: hours ?? this.hours, days: days ?? this.days, slot: slot ?? this.slot,
        reminder: reminder ?? this.reminder,
      );

  /// Can the student press Continue on [s]?
  bool canContinue(int s) => switch (s) {
        1 => name.trim().length >= 2 && gender != null,
        2 => age >= 15,
        3 => outsideKerala ? state.trim().length > 1 : district != null,
        4 => qualification != null,
        5 => exams.isNotEmpty,
        6 => exams.every(posts.containsKey),
        _ => true,
      };

  Json toBody(String language) => {
        'avatar': avatar,
        'name': name.trim(),
        'gender': gender,
        'email': email.trim().isEmpty ? null : email.trim(),
        'dob': '${dob.year}-${dob.month.toString().padLeft(2, '0')}-${dob.day.toString().padLeft(2, '0')}',
        'district': outsideKerala ? null : district,
        'outside_kerala': outsideKerala,
        'state': outsideKerala ? state : null,
        'town': town.isEmpty ? null : town,
        'pincode': pincode.length == 6 ? pincode : null,
        'qualification': qualification,
        'exams': exams,
        'target_posts': posts,
        'level': level,
        'aim': aim,
        'attempt': attempt,
        'study_hours': hours,
        'study_days': [for (final d in days) d ? 1 : 0],
        'study_slot': slot,
        'reminder': reminder,
        'language': language,
      };
}

final setupControllerProvider = NotifierProvider<SetupController, SetupDraft>(SetupController.new);

/// Plan returned by POST /profile/setup (shown on the "plan ready" screen).
final setupPlanProvider = NotifierProvider<PlanHolder, Json?>(PlanHolder.new);

class PlanHolder extends Notifier<Json?> {
  @override
  Json? build() => null;
  void set(Json? v) => state = v;
}

class SetupController extends Notifier<SetupDraft> {
  static const totalSteps = 9;

  @override
  SetupDraft build() {
    final user = ref.read(currentUserProvider);
    return SetupDraft(name: user?.json.s('name') ?? '');
  }

  void update(SetupDraft Function(SetupDraft d) f) => state = f(state);
  void goTo(int step) => state = state.copyWith(step: step.clamp(0, totalSteps - 1));
  void next() => goTo(state.step + 1);
  void back() => goTo(state.step - 1);

  void toggleExam(String id, int max) {
    final list = [...state.exams];
    if (list.contains(id)) {
      list.remove(id);
      final posts = Map<String, String>.of(state.posts)..remove(id);
      state = state.copyWith(exams: list, posts: posts);
    } else if (list.length < max) {
      state = state.copyWith(exams: [...list, id]);
    }
  }

  void setPost(String exam, String post) => state = state.copyWith(posts: {...state.posts, exam: post});

  void toggleDay(int i) {
    final d = [...state.days];
    if (d[i] && state.studyDays == 1) return;
    d[i] = !d[i];
    state = state.copyWith(days: d);
  }

  /// Sends everything to the API and logs the user in fully.
  Future<void> submit() async {
    final lang = ref.read(authControllerProvider).language;
    final res = await ref.read(setupRepositoryProvider).submit(state.toBody(lang));
    ref.read(setupPlanProvider.notifier).set(res.m('plan'));
    ref.read(authControllerProvider.notifier).completeSetup(AppUser(res.m('user')));
  }
}
